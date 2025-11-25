<?php

namespace App\Objects;

use App\Dto\KeepaInputDto;
use Keepa\API\Response;
use Psr\Log\LoggerInterface;
use Keepa\objects\Product;
use Keepa\helper\KeepaTime;

class AsinsOutput
{


    public static function fromResponse(Response $response, KeepaInputDto $keepaInputDto, LoggerInterface $logger): array
    {
        if ($response->products === null) {
            $logger->error("Kafka: No products found in response.");
            return [];
        }

        $timeFrom = $keepaInputDto->time_from;
        $timeTo   = $keepaInputDto->time_to;

        return array_filter(array_map(
            function (Product $product) use ($logger, $keepaInputDto, $timeFrom, $timeTo) {
                return [
                    'asin'              => $product->asin,
                    'brand'             => $product->brand,
                    'title'             => $product->title,
                    'upc_list'          => is_array($product->upcList) ? $product->upcList : [],
                    'ean_list'          => is_array($product->eanList) ? $product->eanList : [],
                    'search_request_id' => $keepaInputDto->id,
                    'domain_id'         => $keepaInputDto->domain_id,
                    'json_data'         => json_encode($product),

                    'time_from' => $timeFrom,
                    'time_to'   => $timeTo,

                    'version'  => $keepaInputDto->version,
                ];
            },
            $response->products
        ));
    }

}
