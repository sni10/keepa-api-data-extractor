<?php

namespace App\Tests\Unit\Service\Validator;

use App\Dto\KeepaInputDto;
use App\Dto\KeepaOutputDto;
use App\Service\Validator\MessageValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageValidatorTest extends TestCase
{
    /** @var ValidatorInterface&MockObject */
    private ValidatorInterface $validator;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private MessageValidator $messageValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->messageValidator = new MessageValidator(
            $this->validator,
            $this->logger
        );
    }

    public function testValidateInputReturnsDtoWhenValid(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => 1,
            'domain_id' => 1,
            'brand' => 'Nike',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'status' => 'PENDING',
            'step' => 0,
        ]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $this->logger
            ->expects(self::never())
            ->method('warning');

        $result = $this->messageValidator->validateInput($dto);

        self::assertSame($dto, $result);
    }

    public function testValidateInputReturnsNullWhenInvalid(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => 1,
            'domain_id' => 1,
            'brand' => '',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'status' => 'PENDING',
            'step' => 0,
        ]);

        $violation = new ConstraintViolation(
            'Brand should not be blank',
            null,
            [],
            $dto,
            'brand',
            ''
        );
        $violations = new ConstraintViolationList([$violation]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Invalid Kafka message',
                self::callback(function ($context) {
                    return isset($context['errors']) && isset($context['payload']);
                })
            );

        $result = $this->messageValidator->validateInput($dto);

        self::assertNull($result);
    }

    public function testValidateOutputReturnsDtoWhenValid(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'B08N5WRWNW';
        $dto->brand = 'Adidas';
        $dto->domain_id = 1;

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $this->logger
            ->expects(self::never())
            ->method('warning');

        $result = $this->messageValidator->validateOutput($dto);

        self::assertSame($dto, $result);
    }

    public function testValidateOutputReturnsNullWhenInvalid(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'INVALID_ASIN_THAT_IS_TOO_LONG_FOR_VALIDATION';
        $dto->brand = 'Test';

        $violation = new ConstraintViolation(
            'ASIN is too long',
            null,
            [],
            $dto,
            'asin',
            'INVALID_ASIN_THAT_IS_TOO_LONG_FOR_VALIDATION'
        );
        $violations = new ConstraintViolationList([$violation]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Invalid message',
                self::callback(function ($context) {
                    return isset($context['errors']) && isset($context['payload']);
                })
            );

        $result = $this->messageValidator->validateOutput($dto);

        self::assertNull($result);
    }

    public function testValidateInputWithMultipleViolations(): void
    {
        $dto = new KeepaInputDto();
        $dto->id = -1; // invalid: should be positive
        $dto->domain_id = 0; // invalid: should be positive
        $dto->brand = '';
        $dto->time_from = 'invalid-date';
        $dto->time_to = 'invalid-date';
        $dto->status = 'INVALID_STATUS';
        $dto->step = -1; // invalid: should be positive or zero

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid id', null, [], $dto, 'id', -1),
            new ConstraintViolation('Invalid domain_id', null, [], $dto, 'domain_id', 0),
            new ConstraintViolation('Brand is blank', null, [], $dto, 'brand', ''),
        ]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->logger
            ->expects(self::once())
            ->method('warning');

        $result = $this->messageValidator->validateInput($dto);

        self::assertNull($result);
    }
}
