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

    public function jsonSchema(string $mode = 'extract'): array
    {
        $path = $this->promptConfig($mode)['schema'] ?? null;

        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException("JSON schema file is missing for mode [{$mode}].");
        }

        $schema = json_decode((string) file_get_contents($path), true);

        if (! is_array($schema)) {
            throw new RuntimeException("JSON schema file is invalid for mode [{$mode}].");
        }

        return $schema;
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
