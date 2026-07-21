<?php

namespace App\Services;

final readonly class PaymentProofVerificationResult
{
    public function __construct(
        public bool $exactMatch,
        public ?string $detectedOrderNumber = null,
        public ?int $detectedAmountCents = null,
        public ?string $source = null,
    ) {}
}
