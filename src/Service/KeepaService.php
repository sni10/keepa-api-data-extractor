<?php

namespace App\Service;

use App\Dto\KeepaInputDto;
use App\Dto\KeepaOutputDto;
use App\Objects\AsinsOutput;
use App\Exception\KeepaRequestFailedException;
use App\Exception\NotEnoughTokenError;
use App\Exception\KeepaTokenTimeoutException;
use Exception;
use Keepa\API\Request;
use Keepa\API\Response;
use Keepa\API\ResponseStatus;
use Keepa\helper\KeepaTime;
use Keepa\KeepaAPI;
use Keepa\objects\ProductFinderRequest;
use Psr\Log\LoggerInterface;

class KeepaService
{
    const ASINS_PER_REQUEST = 10;
    const PER_PAGE = 100;
    const MAX_PAGES = 100;
    const MULTIPLIER_IN_MILISECONDS = 1000;
    private KeepaAPI $api;
    private int $amazon_locale;
    private int $tokenLimit;
    private int $waitTimeout;
    private int $maxRetries;
    private int $maxRequestRetries;
    private string $time_from;
    private string $time_to;
    private string $brand;
    private LoggerInterface $logger;
    private LoggerInterface $tokenLogger;

    // Stats for current execution session
    private int $tokensUsedAcc = 0;
    private int $pagesProcessed = 0;
    private int $asinsFoundAcc = 0;
    private int $sleepSecondsAcc = 0;
    private ?int $lastTokensLeft = null;
    private array $pageStats = [];

    /**
     * @throws Exception
     */
    public function __construct(string $apiKey, LoggerInterface $tokenLogger, LoggerInterface $logger)
    {
        $this->api = new KeepaAPI($apiKey);
        $this->logger = $logger;
        $this->tokenLogger = $tokenLogger;
        $this->tokenLimit = (int) getenv('KEEPA_TOKEN_MIN_LIMIT');
        $this->waitTimeout = (int) getenv('KEEPA_TOKEN_WAIT_TIMEOUT');
        $this->maxRetries = (int) getenv('KEEPA_TOKEN_MAX_RETRIES');
        $this->maxRequestRetries = (int) getenv('KEEPA_REQUEST_MAX_RETRIES');
    }

    /**
     * @throws Exception
     */
    public function execute(KeepaInputDto $keepaInputDto): \Generator
    {
        $this->amazon_locale = $keepaInputDto->domain_id;
        $this->time_from = $keepaInputDto->time_from;
        $this->time_to = $keepaInputDto->time_to;
        $this->brand = $keepaInputDto->brand;

        // reset stats for this run
        $this->tokensUsedAcc = 0;
        $this->pagesProcessed = 0;
        $this->asinsFoundAcc = 0;
        $this->sleepSecondsAcc = 0;
        $this->lastTokensLeft = null;
        $this->pageStats = [];

        $searchedAsinsGenerator = $this->findProducts();

        $hasResults = false;
        // важно! разбор асинов - yield from $this->getAsinsData($searchedAsins, $keepaInputDto);
        // Вложенный цикл foreach для обработки генератора
        foreach ($searchedAsinsGenerator as $asinsBatch) {
            foreach ($asinsBatch as $asin) {
                $hasResults = true;
                yield KeepaOutputDto::fromArray([
                    'asin'              => $asin,
                    'brand'             => $keepaInputDto->brand,
                    'title'             => null, # можем только из распаршеного асина извлечь
                    'upc_list'          => null, # соответсвенно
                    'ean_list'          => null, # соответсвенно
                    'search_request_id' => $keepaInputDto->id,
                    'domain_id'         => $keepaInputDto->domain_id,
                    'json_data'         => null,
                    'time_from'         => $keepaInputDto->time_from,
                    'time_to'           => $keepaInputDto->time_to,
                    'version'           => $keepaInputDto->version,
                ]);
            }
        }
        
        if (!$hasResults) {
            yield from [];
        }
    }

    /**
     * @throws Exception
     */
    private function findProducts(): \Generator
    {
        $page = 0;
        $maxPages = self::MAX_PAGES;

        do {
            // ensure tokens before each page
            $waitStart = microtime(true);
            try {
                $this->hasEnoughTokens();
            } catch (KeepaTokenTimeoutException $e) {
                $this->tokenLogger->error('Токены недоступны в течение ожидания: ' . $e->getMessage());
                return ;
            } finally {
                $this->sleepSecondsAcc += (int) round(microtime(true) - $waitStart);
            }

            $pageTokensBefore = $this->tokensUsedAcc;

            try {
                $resultFinder = $this->findProductsRequest($page);
            } catch (NotEnoughTokenError $e) {
                $this->tokenLogger->warning(sprintf('Недостаточно токенов при запросе страницы %d: %s', $page, $e->getMessage()));
                break; // прерываем пакетную обработку
            } catch (KeepaRequestFailedException $e) {
                $this->logger->error(sprintf('Ошибка запроса Keepa на странице %d: %s', $page, $e->getMessage()));
                $page++; // пропускаем страницу
                continue;
            } catch (Exception $e) {
                $this->logger->error(sprintf('Неожиданная ошибка на странице %d: %s', $page, $e->getMessage()));
                $page++;
                continue;
            }

            if ($resultFinder->totalResults === 0) {
                break;
            }

            if(($resultFinder->totalResults ?? 0) < self::PER_PAGE * self::MAX_PAGES && $page == 0) {
                $maxPages = (int) ceil(($resultFinder->totalResults ?? 0) / self::PER_PAGE);
            }

            $asinList = $resultFinder->asinList ?? [];
            $asinsOnPage = is_array($asinList) ? count($asinList) : 0;
            $this->asinsFoundAcc += $asinsOnPage;
            $this->pagesProcessed++;
            $pageTokensSpent = max(0, $this->tokensUsedAcc - $pageTokensBefore);
            $this->pageStats[] = [
                'page' => $page,
                'asins' => $asinsOnPage,
                'tokens' => $pageTokensSpent,
                'tokensLeft' => $this->lastTokensLeft,
            ];
            $this->logger->info(sprintf('Keepa page %d: asins=%d, tokens_spent=%d, tokens_left=%s', $page, $asinsOnPage, $pageTokensSpent, (string)($this->lastTokensLeft ?? 'n/a')));

            yield $asinList;
            $page++;
        } while ($page < $maxPages);
    }

    /**
     * @throws Exception
     */
    private function findProductsRequest(int $page): Response
    {
        $trackingSinceLte = strtotime($this->time_to) * self::MULTIPLIER_IN_MILISECONDS;
        $trackingSinceGte = strtotime($this->time_from) * self::MULTIPLIER_IN_MILISECONDS;

        $productFinderRequest = new ProductFinderRequest(); // TODO 1: new = every 10 tokens 1

        $productFinderRequest->brand = $this->brand;

        $productFinderRequest->trackingSince_lte = KeepaTime::unixInMillisToKeepaMinutes($trackingSinceLte);
        $productFinderRequest->trackingSince_gte = KeepaTime::unixInMillisToKeepaMinutes($trackingSinceGte);

        $productFinderRequest->perPage = self::PER_PAGE;
        $productFinderRequest->page = $page;

        $request = Request::getFinderRequest($this->amazon_locale, $productFinderRequest);

        return $this->makeRequest($request);
    }


    /**
     * @throws KeepaRequestFailedException
     * @throws NotEnoughTokenError
     */
    private function makeRequest(Request $request): Response
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRequestRetries) {
            $attempt++;
            try {
                // Preflight token status only once to seed lastTokensLeft
                $tokensBefore = $this->lastTokensLeft;
                if ($tokensBefore === null) {
                    $tokensBefore = $this->getRemainingTokens();
                    $this->lastTokensLeft = $tokensBefore;
                }

                $response = $this->api->sendRequest($request);
                $this->logger->info('KEEPA_RAW_RESPONSE', ['response' => json_decode(json_encode($response), true)]);

                switch ($response->status) {
                    case ResponseStatus::OK:
                        // Determine tokens after request
                        if (isset($response->tokensLeft)) {
                            $tokensAfter = (int) $response->tokensLeft;
                        } else {
                            $tokensAfter = $this->getRemainingTokens();
                        }
                        $delta = $tokensBefore - $tokensAfter;
                        if ($delta > 0) {
                            $this->tokensUsedAcc += $delta;
                        }
                        $this->lastTokensLeft = $tokensAfter;
                        return $response;
                    case ResponseStatus::NOT_ENOUGH_TOKEN:
                        $message = $response->error->message ?? 'Not enough tokens';
                        $this->tokenLogger->warning('Keepa NOT ENOUGH TOKEN: ' . $message);
                        throw new NotEnoughTokenError($message);
                    case ResponseStatus::REQUEST_FAILED:
                    case ResponseStatus::REQUEST_REJECTED:
                        // transient errors - retry
                        $message = $response->error->message ?? 'Request failed';
                        $this->logger->warning(sprintf('Keepa transient error on attempt %d/%d: %s', $attempt, $this->maxRequestRetries, $message));
                        if ($attempt >= $this->maxRequestRetries) {
                            throw new KeepaRequestFailedException($message);
                        }
                        sleep(1 * $attempt);
                        continue 2;
                    case ResponseStatus::FAIL:
                    case ResponseStatus::METHOD_NOT_ALLOWED:
                    case ResponseStatus::PAYMENT_REQUIRED:
                    default:
                        $message = $response->error->message ?? 'Unknown error';
                        $this->logger->error('Keepa error: ' . $message);
                        throw new KeepaRequestFailedException($message);
                }
            } catch (Exception $e) {
                // network or client exception
                $lastException = $e;
                $this->logger->warning(sprintf('Keepa exception on attempt %d/%d: %s', $attempt, $this->maxRequestRetries, $e->getMessage()));
                if ($attempt >= $this->maxRequestRetries) {
                    throw new KeepaRequestFailedException($e->getMessage(), 0, $e);
                }
                sleep(1 * $attempt);
            }
        }

        // if loop exits unexpectedly
        throw new KeepaRequestFailedException($lastException?->getMessage() ?? 'Unknown Keepa error');
    }

    /**
     * Проверяет наличие достаточного количества токенов.
     * Ожидает появления токенов с ограничением по времени и количеству попыток.
     *
     * @throws KeepaTokenTimeoutException
     */
    private function hasEnoughTokens(): void
    {
        $attempt = 0;
        $lastTokensLeft = null; // запоминаем последнее известное значение токенов
        while ($attempt < $this->maxRetries) {
            $attempt++;
            $start = time();
            do {
                try {
                    $tokenRequest = Request::getTokenStatusRequest();
                    $response = $this->api->sendRequest($tokenRequest);

                    if ($response !== null && $response->status === ResponseStatus::OK) {
                        $tokensLeft = $response->tokensLeft ?? 0;
                        $lastTokensLeft = $tokensLeft; // сохраняем
                        if ($tokensLeft >= $this->tokenLimit) {
                            $this->tokenLogger->info(sprintf('Доступно токенов: %d (порог %d)', $tokensLeft, $this->tokenLimit));
                            return;
                        }
                        $this->tokenLogger->warning(sprintf('Токенов недостаточно (%d < %d). Ожидаем...', $tokensLeft, $this->tokenLimit));
                    } else {
                        $this->logger->warning('Не удалось получить статус токенов');
                    }
                } catch (\Throwable $t) {
                    $this->logger->warning('Исключение при получении статуса токенов: ' . $t->getMessage(), ['exception' => $t]);
                }

                sleep(1);
            } while ((time() - $start) < $this->waitTimeout);

            $this->tokenLogger->warning(sprintf('Токены не появились за %d сек (попытка %d/%d).', $this->waitTimeout, $attempt, $this->maxRetries));
        }

        $detail = $lastTokensLeft !== null
            ? sprintf(' Токенов недостаточно (%d < %d).', $lastTokensLeft, $this->tokenLimit)
            : '';

        throw new KeepaTokenTimeoutException(sprintf('Токены недоступны после %d попыток ожидания по %d сек.%s', $this->maxRetries, $this->waitTimeout, $detail));
    }

    /**
     * @throws Exception
     */
    private function getAsinsData(array $asins, KeepaInputDto $keepaInputDto): \Generator
    {
        $chunks = array_chunk($asins, self::ASINS_PER_REQUEST);

        foreach ($chunks as $chunk) {
            try {
                $chunkData = $this->getAsinsDataRequest($chunk);
                if (empty($chunkData->products)) {
                    return;
                }
                foreach (AsinsOutput::fromResponse($chunkData, $keepaInputDto, $this->logger) as $formattedProduct) {
                    yield $formattedProduct;
                }
            } catch (NotEnoughTokenError $e) {
                $this->tokenLogger->warning('Недостаточно токенов при получении данных ASIN: ' . $e->getMessage());
                break;
            } catch (KeepaRequestFailedException $e) {
                $this->logger->error('Ошибка Keepa при получении данных ASIN: ' . $e->getMessage());
                continue;
            } catch (Exception $e) {
                $this->logger->error('Неожиданная ошибка при получении данных ASIN: ' . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * @throws KeepaRequestFailedException
     * @throws NotEnoughTokenError
     */
    private function getAsinsDataRequest(array $asins): Response
    {
        $request = Request::getProductRequest($this->amazon_locale, 0, '', '', 0, false, $asins);

        return $this->makeRequest($request);
    }

    public function getRemainingTokens(): int
    {
        try {
            $tokenRequest = Request::getTokenStatusRequest();
            $response = $this->api->sendRequest($tokenRequest);
            if ($response !== null && $response->status === ResponseStatus::OK) {
                return (int) ($response->tokensLeft ?? 0);
            }
            $this->logger->warning('Не удалось получить статус токенов');
        } catch (\Throwable $t) {
            $this->logger->warning('Исключение при получении статуса токенов: ' . $t->getMessage(), ['exception' => $t]);
        }
        return 0;
    }

    public function getStats(): array
    {
        return [
            'tokensUsed'     => $this->tokensUsedAcc,
            'pagesProcessed' => $this->pagesProcessed,
            'asinsFound'     => $this->asinsFoundAcc,
            'sleepSeconds'   => $this->sleepSecondsAcc,
            'tokensLeft'     => $this->lastTokensLeft,
            'pageStats'      => $this->pageStats,
        ];
    }
}