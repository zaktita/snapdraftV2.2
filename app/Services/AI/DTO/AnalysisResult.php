<?php

namespace App\Services\AI\DTO;

class AnalysisResult
{
    public function __construct(
        public readonly string $rawText,
        public readonly ?string $analysisProse,
        public readonly ?array $promptJson,
        public readonly array $tweaks,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly ?float $estimatedCostUsd,
        public readonly int $latencyMs,
        public readonly bool $jsonValid,
        public readonly array $jsonValidationErrors,
        public readonly ?string $summary = null,
    ) {}
}
