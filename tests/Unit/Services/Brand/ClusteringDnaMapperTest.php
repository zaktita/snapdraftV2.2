<?php

namespace Tests\Unit\Services\Brand;

use App\Services\Brand\ClusteringDnaMapper;
use PHPUnit\Framework\TestCase;

class ClusteringDnaMapperTest extends TestCase
{
    public function test_maps_cluster_result_to_dna_with_whole_cluster_images(): void
    {
        $mapper = new ClusteringDnaMapper();

        $mapped = $mapper->toDnaJson([
            'globalAnalysis' => 'Bold educational brand.',
            'globalRules' => ['Use brand footer'],
            'clusters' => [
                [
                    'name' => 'Event Promo',
                    'tags' => ['event', 'promo'],
                    'reason' => 'Photo + headline layout',
                    'imageIndices' => [0, 1, 2],
                    'dominantColor' => '#EA6E2A',
                    'palette' => ['#EA6E2A', '#FFFFFF'],
                    'typographyStyle' => 'Bold sans-serif',
                    'compositionType' => 'Split layout',
                    'backgroundTreatment' => 'Photo + color block',
                    'textPlacement' => 'Headline top-left',
                    'renderingStyle' => 'photorealistic lifestyle photography',
                    'photoTreatment' => 'full-bleed with soft bokeh',
                    'graphicDevices' => ['headline overlay on photo', 'logo pill bottom-center'],
                    'typographyDetails' => 'Bold sans-serif headline top-right',
                    'layoutSkeleton' => 'Full-bleed photo with headline overlay and logo pill at bottom',
                    'logoTreatment' => 'White pill lockup bottom-center',
                    'textDensity' => 'minimal',
                ],
            ],
        ], 'Test Brand');

        $this->assertCount(1, $mapped['dna']['clusters']);
        $this->assertCount(3, $mapped['dna']['clusters'][0]['images']);
        $this->assertSame([0, 1, 2], array_column($mapped['dna']['clusters'][0]['images'], 'position'));
        $this->assertTrue($mapped['dna']['clusters'][0]['images'][0]['is_anchor']);
        $this->assertSame('photorealistic lifestyle photography', $mapped['dna']['clusters'][0]['visual']['rendering_style']);
        $this->assertSame('minimal', $mapped['dna']['clusters'][0]['visual']['text_density']);
        $this->assertSame(['headline overlay on photo', 'logo pill bottom-center'], $mapped['dna']['clusters'][0]['visual']['graphic_devices']);
    }
}
