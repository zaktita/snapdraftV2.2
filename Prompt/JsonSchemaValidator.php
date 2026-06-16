<?php

namespace App\Services\Prompt;

class JsonSchemaValidator
{
    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(?array $json, string $mode = 'extract'): array
    {
        if ($json === null) {
            return ['valid' => false, 'errors' => ['json is null']];
        }

        return match ($mode) {
            'extract' => $this->validateExtract($json),
            'generate_post' => $this->validatePostPrompt($json),
            default => ['valid' => false, 'errors' => ["Unknown validation mode [{$mode}]"]],
        };
    }

    /**
     * @return array{valid: bool, errors: list<string>}
     */
    protected function validateExtract(array $json): array
    {
        $errors = [];

        if (! isset($json['brand']['name']) || ! is_string($json['brand']['name']) || trim($json['brand']['name']) === '') {
            $errors[] = 'Missing brand.name';
        }

        if (! isset($json['rules']['locked']) || ! is_array($json['rules']['locked']) || count($json['rules']['locked']) < 1) {
            $errors[] = 'Missing rules.locked array';
        }

        if (! isset($json['clusters']) || ! is_array($json['clusters']) || count($json['clusters']) < 1) {
            $errors[] = 'Missing clusters array';
        } else {
            foreach ($json['clusters'] as $i => $cluster) {
                if (! is_array($cluster)) {
                    $errors[] = "Cluster {$i} is not an object";
                    continue;
                }
                if (empty($cluster['key'])) {
                    $errors[] = "Cluster {$i} missing key";
                }
                if (empty($cluster['images']) || ! is_array($cluster['images'])) {
                    $errors[] = "Cluster {$i} missing images";
                }
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * @return array{valid: bool, errors: list<string>}
     */
    protected function validatePostPrompt(array $json): array
    {
        $errors = [];

        if (! isset($json['brand_locked']) || ! is_array($json['brand_locked'])) {
            $errors[] = 'Missing brand_locked';
        }

        if (! isset($json['post']['concept']) || ! is_string($json['post']['concept']) || trim($json['post']['concept']) === '') {
            $errors[] = 'Missing or empty post.concept';
        }

        $include = $json['quality']['include'] ?? $json['quality']['must_have'] ?? null;
        if (! is_array($include) || count($include) < 1) {
            $errors[] = 'Missing quality.include (or quality.must_have) array';
        }

        $avoid = $json['quality']['avoid'] ?? [];
        if (! is_array($avoid)) {
            $errors[] = 'quality.avoid must be an array when present';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
