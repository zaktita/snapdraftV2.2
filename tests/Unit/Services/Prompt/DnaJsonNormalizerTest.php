<?php

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\DnaJsonNormalizer;
use App\Services\Prompt\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

class DnaJsonNormalizerTest extends TestCase
{
    public function test_normalizes_missing_rules_locked_from_visual_identity(): void
    {
        $normalizer = new DnaJsonNormalizer();
        $validator = new JsonSchemaValidator();

        $dna = $normalizer->normalize([
            'brand' => ['name' => 'Test Brand'],
            'visual_identity' => [
                'composition' => ['layout_system' => 'Two-column split'],
                'recurring_motifs' => ['Lime pill'],
            ],
            'clusters' => [
                [
                    'key' => 'events',
                    'label' => 'Events',
                    'images' => [['position' => 0, 'label' => 'Post 1', 'is_anchor' => true]],
                ],
            ],
        ], 'Test Brand', 3);

        $result = $validator->validate($dna, 'extract');

        $this->assertTrue($result['valid'], implode(', ', $result['errors']));
        $this->assertNotEmpty($dna['rules']['locked']);
    }

    public function test_creates_default_cluster_when_missing(): void
    {
        $normalizer = new DnaJsonNormalizer();

        $dna = $normalizer->normalize([
            'brand' => ['name' => 'Acme'],
            'rules' => ['locked' => ['Keep logo visible']],
        ], 'Acme', 2);

        $this->assertCount(1, $dna['clusters']);
        $this->assertCount(2, $dna['clusters'][0]['images']);
    }
}
