<?php

namespace App\Services\AI;

use App\Services\PromptService;
use RuntimeException;

class ClusteringService
{
    public function __construct(
        protected GeminiClient $client,
        protected PromptService $promptService,
    ) {}

    protected function model(): string
    {
        return config('services.gemini.model', 'gemini-3.1-pro-preview');
    }

    /**
     * Phase 1: Cluster brand reference images.
     *
     * @param  string[]  $storagePaths  Paths relative to storage/app/public
     * @return array  ClusterResponse shape: { globalAnalysis, globalRules, clusters[] }
     */
    public function cluster(array $storagePaths): array
    {
        if (count($storagePaths) < 3) {
            throw new RuntimeException('At least 3 reference images are required for clustering.');
        }

        $imageParts = array_map(
            fn(string $path) => $this->client->imageToInlinePart($path),
            $storagePaths
        );

        $prompt = $this->promptService->render('clustering', [
            'image_count' => count($storagePaths),
        ]);

        $contents = [[
            'parts' => [...$imageParts, ['text' => $prompt]],
        ]];

        $schema = [
            'type' => 'object',
            'properties' => [
                'globalAnalysis' => ['type' => 'string'],
                'globalRules'    => ['type' => 'array', 'items' => ['type' => 'string']],
                'clusters'       => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'                => ['type' => 'string'],
                            'tags'                => ['type' => 'array', 'items' => ['type' => 'string']],
                            'reason'              => ['type' => 'string'],
                            'imageIndices'        => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'dominantColor'       => ['type' => 'string'],
                            'typographyStyle'     => ['type' => 'string'],
                            'compositionType'     => ['type' => 'string'],
                            'backgroundTreatment' => ['type' => 'string'],
                            'palette'             => ['type' => 'array', 'items' => ['type' => 'string']],
                            'textPlacement'       => ['type' => 'string'],
                            'renderingStyle'      => ['type' => 'string'],
                            'photoTreatment'      => ['type' => 'string'],
                            'graphicDevices'      => ['type' => 'array', 'items' => ['type' => 'string']],
                            'typographyDetails'   => ['type' => 'string'],
                            'layoutSkeleton'      => ['type' => 'string'],
                            'logoTreatment'       => ['type' => 'string'],
                            'textDensity'         => ['type' => 'string'],
                        ],
                        'required' => [
                            'name', 'tags', 'reason', 'imageIndices', 'dominantColor',
                            'typographyStyle', 'compositionType', 'backgroundTreatment',
                            'palette', 'textPlacement', 'renderingStyle', 'photoTreatment',
                            'graphicDevices', 'typographyDetails', 'layoutSkeleton',
                            'logoTreatment', 'textDensity',
                        ],
                    ],
                ],
            ],
            'required' => ['globalAnalysis', 'globalRules', 'clusters'],
        ];

        $result = $this->client->generateContentWithSchema($this->model(), $contents, $schema);

        $this->validateClusters($result, count($storagePaths));

        return $result;
    }

    /**
     * Mirrors test.ts validateClusters(): each image assigned to exactly one cluster,
     * each cluster has at least min_images_per_cluster images.
     */
    private function validateClusters(array $result, int $imageCount): void
    {
        $min = (int) config('ai.cluster_selection.min_images_per_cluster', 3);
        $seen = [];

        foreach ($result['clusters'] as $i => $cluster) {
            $count = count($cluster['imageIndices']);

            if ($count < $min) {
                throw new RuntimeException(
                    "Cluster {$i} (\"{$cluster['name']}\") has {$count} images — must be at least {$min}."
                );
            }

            foreach ($cluster['imageIndices'] as $idx) {
                if ($idx < 0 || $idx >= $imageCount) {
                    throw new RuntimeException(
                        "Cluster {$i} references out-of-bounds image index {$idx}."
                    );
                }

                if (in_array($idx, $seen, true)) {
                    throw new RuntimeException(
                        "Image index {$idx} assigned to multiple clusters."
                    );
                }

                $seen[] = $idx;
            }
        }

        for ($i = 0; $i < $imageCount; $i++) {
            if (!in_array($i, $seen, true)) {
                throw new RuntimeException("Image index {$i} was not assigned to any cluster.");
            }
        }
    }
}
