<?php

namespace App\Services\Brand;

use Illuminate\Support\Str;

class ClusteringDnaMapper
{
    /**
     * Map ClusteringService output to Brand DNA JSON for ProjectBrandPersister.
     *
     * @param  array{globalAnalysis: string, globalRules: list<string>, clusters: list<array<string, mixed>>}  $clusterResult
     * @return array{dna: array<string, mixed>, summary: string}
     */
    public function toDnaJson(array $clusterResult, string $brandName): array
    {
        $slug = Str::slug($brandName) ?: 'brand';

        $clusters = [];
        foreach ($clusterResult['clusters'] as $index => $cluster) {
            $images = [];
            foreach ($cluster['imageIndices'] as $posInCluster => $imageIndex) {
                $images[] = [
                    'position' => (int) $imageIndex,
                    'label' => 'Reference '.((int) $imageIndex + 1),
                    'is_anchor' => $posInCluster === 0,
                ];
            }

            $clusters[] = [
                'key' => Str::slug((string) ($cluster['name'] ?? 'cluster')).'-'.$index,
                'label' => (string) ($cluster['name'] ?? 'Cluster '.($index + 1)),
                'summary' => (string) ($cluster['reason'] ?? ''),
                'keywords' => array_values($cluster['tags'] ?? []),
                'images' => $images,
                'visual' => [
                    'dominant_color' => $cluster['dominantColor'] ?? null,
                    'palette' => $cluster['palette'] ?? [],
                    'typography_style' => $cluster['typographyStyle'] ?? null,
                    'composition_type' => $cluster['compositionType'] ?? null,
                    'background_treatment' => $cluster['backgroundTreatment'] ?? null,
                    'text_placement' => $cluster['textPlacement'] ?? null,
                    'rendering_style' => $cluster['renderingStyle'] ?? null,
                    'photo_treatment' => $cluster['photoTreatment'] ?? null,
                    'graphic_devices' => $cluster['graphicDevices'] ?? [],
                    'typography_details' => $cluster['typographyDetails'] ?? null,
                    'layout_skeleton' => $cluster['layoutSkeleton'] ?? null,
                    'logo_treatment' => $cluster['logoTreatment'] ?? null,
                    'text_density' => $cluster['textDensity'] ?? null,
                ],
            ];
        }

        $dna = [
            'brand' => [
                'name' => $brandName,
                'slug' => $slug,
                'positioning' => $clusterResult['globalAnalysis'] ?? null,
            ],
            'rules' => [
                'locked' => array_values($clusterResult['globalRules'] ?? []),
                'flex' => [],
            ],
            'clusters' => $clusters,
        ];

        $summary = $this->buildSummary($clusterResult, $clusters);

        return ['dna' => $dna, 'summary' => $summary];
    }

    /**
     * @param  list<array<string, mixed>>  $clusters
     */
    protected function buildSummary(array $clusterResult, array $clusters): string
    {
        $lines = [
            $clusterResult['globalAnalysis'] ?? 'Brand clustering complete.',
        ];

        foreach ($clusterResult['globalRules'] ?? [] as $rule) {
            $lines[] = '• '.$rule;
        }

        foreach ($clusters as $cluster) {
            $imageCount = count($cluster['images'] ?? []);
            $lines[] = '• Cluster "'.$cluster['label'].'": '.$imageCount.' reference(s)';
        }

        return implode("\n", $lines);
    }
}
