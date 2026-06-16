<?php

namespace App\Services\Prompt;

class AnalysisResponseParser
{
    public function __construct(
        protected JsonSchemaValidator $validator,
        protected SkillPromptBuilder $promptBuilder,
    ) {}

    /**
     * @return array{
     *     analysis_prose: ?string,
     *     prompt_json: ?array,
     *     tweaks: list<string>,
     *     summary: ?string,
     *     json_valid: bool,
     *     json_validation_errors: list<string>
     * }
     */
    public function parse(string $rawText, string $mode = 'extract'): array
    {
        $config = $this->promptBuilder->promptConfig($mode);

        $analysisProse = $this->extractSection($rawText, $config['prose_heading']);
        $jsonBlock = $this->extractJsonBlock($rawText, $config['json_heading']);
        $tweaks = $this->extractBullets($rawText, $config['summary_heading']);

        $summary = $mode === 'extract'
            ? $this->extractSection($rawText, $config['summary_heading'])
            : null;

        $promptJson = null;
        $validation = ['valid' => false, 'errors' => ['No JSON block found']];

        if ($jsonBlock !== null) {
            $decoded = json_decode($jsonBlock, true);
            if (is_array($decoded)) {
                $promptJson = $decoded;
                $validation = $this->validator->validate($promptJson, $mode);
            } else {
                $validation = ['valid' => false, 'errors' => ['JSON block is not valid JSON']];
            }
        }

        return [
            'analysis_prose' => $analysisProse,
            'prompt_json' => $promptJson,
            'tweaks' => $tweaks,
            'summary' => $summary,
            'json_valid' => $validation['valid'],
            'json_validation_errors' => $validation['errors'],
        ];
    }

    protected function extractSection(string $text, string $heading): ?string
    {
        $pattern = '/#{1,3}\s*'.preg_quote($heading, '/').'\s*\n+([\s\S]*?)(?=\n#{1,3}\s|\z)/iu';

        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractJsonBlock(string $text, string $heading): ?string
    {
        $headingPattern = preg_quote($heading, '/');
        if (preg_match('/#{1,3}\s*'.$headingPattern.'\s*\n+```(?:json)?\s*([\s\S]*?)```/iu', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(\{[\s\S]*\})\s*$/', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function extractBullets(string $text, string $heading): array
    {
        $section = $this->extractSection($text, $heading);

        if ($section === null) {
            return [];
        }

        preg_match_all('/^\s*[-*]\s+(.+)$/m', $section, $matches);

        return array_values(array_filter(array_map('trim', $matches[1] ?? [])));
    }
}
