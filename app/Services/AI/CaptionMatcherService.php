<?php

namespace App\Services\AI;

use App\Services\PromptService;
use RuntimeException;

class CaptionMatcherService
{
    // Maps CSV format value → Gemini aspectRatio + human description
    const FORMAT_MAP = [
        'square'    => ['aspectRatio' => '1:1',  'description' => 'square (1:1)'],
        'portrait'  => ['aspectRatio' => '9:16', 'description' => 'portrait (9:16)'],
        'landscape' => ['aspectRatio' => '16:9', 'description' => 'landscape (16:9)'],
    ];

    public function __construct(
        protected GeminiClient $client,
        protected PromptService $promptService,
    ) {}

    protected function model(): string
    {
        return config('services.gemini.prompt_model', 'gemini-3-flash-preview');
    }

    /**
     * Phase 2: Match each CSV row to a cluster and build per-row generation prompts.
     *
     * @param  array  $clusterResult  Output of ClusteringService::cluster()
     * @param  array  $csvRows        Array of ['title'=>..., 'caption'=>..., 'format'=>...]
     * @return array  Array of PromptBatchItem shapes
     */
    public function match(array $clusterResult, array $csvRows): array
    {
        $clusterContext = implode("\n---\n", array_map(
            function (array $c, int $i) {
                return "Cluster {$i}: \"{$c['name']}\"\n"
                    . "  Tags: " . implode(', ', $c['tags']) . "\n"
                    . "  Dominant: {$c['dominantColor']} | Palette: " . implode(', ', $c['palette']) . "\n"
                    . "  Typography: {$c['typographyStyle']}\n"
                    . "  Composition: {$c['compositionType']}\n"
                    . "  Background: {$c['backgroundTreatment']}\n"
                    . "  Text placement: {$c['textPlacement']}\n"
                    . "  Reason: {$c['reason']}";
            },
            $clusterResult['clusters'],
            array_keys($clusterResult['clusters'])
        ));

        $rowsContext = implode("\n", array_map(
            fn(array $r, int $i) => "Row {$i} | title: {$r['title']} | format: {$r['format']} | caption: {$r['caption']}",
            $csvRows,
            array_keys($csvRows)
        ));

        $prompt = $this->promptService->render('caption-match-batch', [
            'cluster_context'   => $clusterContext,
            'rows_context'      => $rowsContext,
            'max_cluster_index' => count($clusterResult['clusters']) - 1,
        ]);

        $contents = [[
            'parts' => [['text' => $prompt]],
        ]];

        $schema = [
            'type' => 'object',
            'properties' => [
                'matches' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'rowIndex'     => ['type' => 'integer'],
                            'clusterIndex' => ['type' => 'integer'],
                            'overlayText'  => ['type' => 'string'],
                            'explanation'  => ['type' => 'string'],
                        ],
                        'required' => ['rowIndex', 'clusterIndex', 'overlayText', 'explanation'],
                    ],
                ],
            ],
            'required' => ['matches'],
        ];

        $response = $this->client->generateContentWithSchema($this->model(), $contents, $schema);

        $matchByRow = [];
        foreach ($response['matches'] ?? [] as $m) {
            $matchByRow[$m['rowIndex']] = $m;
        }

        $promptBatch = [];

        foreach ($csvRows as $i => $row) {
            $match = $matchByRow[$i] ?? null;

            if (!$match) {
                throw new RuntimeException("No match returned for CSV row {$i}");
            }

            $clusterIndex = $match['clusterIndex'];
            $cluster = $clusterResult['clusters'][$clusterIndex] ?? null;

            if (!$cluster) {
                throw new RuntimeException("Invalid clusterIndex {$clusterIndex} for row {$i}");
            }

            $overlayText = $match['overlayText'] ?: $row['caption'];
            $refIndices  = array_slice($cluster['imageIndices'], 0, 2);

            $promptBatch[] = [
                'rowIndex'              => $i,
                'title'                 => $row['title'],
                'format'                => $row['format'],
                'clusterIndex'          => $clusterIndex,
                'referenceImageIndices' => $refIndices,
                'overlayText'           => $overlayText,
                'generationPrompt'      => $this->buildGenerationPrompt($overlayText, $cluster, $row['format'], $clusterResult['globalRules']),
            ];
        }

        return $promptBatch;
    }

    private function buildGenerationPrompt(string $overlayText, array $cluster, string $format, array $globalRules): string
    {
        $formatInfo = self::FORMAT_MAP[strtolower($format)] ?? self::FORMAT_MAP['square'];

        $rulesBlock = count($globalRules) > 0
            ? implode("\n", array_map(fn($r, $i) => ($i + 1) . ". {$r}", $globalRules, array_keys($globalRules)))
            : 'Maintain strict visual consistency with the reference images.';

        return $this->promptService->render('cluster-generation', [
            'format_description' => $formatInfo['description'],
            'aspect_ratio'       => $formatInfo['aspectRatio'],
            'background_treatment' => $cluster['backgroundTreatment'],
            'dominant_color'     => $cluster['dominantColor'],
            'palette'            => implode(', ', $cluster['palette']),
            'typography_style'   => $cluster['typographyStyle'],
            'text_placement'     => $cluster['textPlacement'],
            'composition_type'   => $cluster['compositionType'],
            'overlay_text'       => $overlayText,
            'cluster_name'       => $cluster['name'],
            'rules_block'        => $rulesBlock,
        ]);
    }
}
