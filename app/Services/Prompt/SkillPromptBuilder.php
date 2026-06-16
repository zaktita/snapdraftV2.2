<?php

namespace App\Services\Prompt;

use RuntimeException;

class SkillPromptBuilder
{
    public function systemPrompt(string $mode = 'extract'): string
    {
        $path = $this->promptConfig($mode)['system'] ?? null;

        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException("System prompt file is missing for mode [{$mode}].");
        }

        return trim((string) file_get_contents($path));
    }

    /**
     * @return array{system: string, schema: string, json_heading: string, prose_heading: string, summary_heading: string}
     */
    public function promptConfig(string $mode): array
    {
        $config = config("ai.prompts.{$mode}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown prompt mode [{$mode}].");
        }

        return $config;
    }
}
