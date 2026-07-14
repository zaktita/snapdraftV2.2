<?php

namespace App\Services\Test;

use App\Services\AI\ClusteringService;
use App\Services\Brand\ClusterCaptionMatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClusteredMasterPromptLabService
{
    public const STORAGE_PREFIX = 'clustered-master-prompt-lab';

    public function __construct(
        protected ClusteringService $clustering,
        protected MasterPromptLabService $masterPromptLab,
        protected ClusterCaptionMatcher $captionMatcher,
    ) {}

    /**
     * Store uploads and run ClusteringService.
     *
     * @param  UploadedFile[]  $images
     * @return array{
     *     images: list<array{index: int, path: string, url: string, name: string}>,
     *     global_analysis: string,
     *     global_rules: list<string>,
     *     clusters: list<array>
     * }
     */
    public function clusterUploads(array $images): array
    {
        if (count($images) < 3) {
            throw new RuntimeException('At least 3 reference images are required.');
        }

        $stored = [];
        foreach ($images as $i => $file) {
            $path = $file->store(self::STORAGE_PREFIX.'/refs', 'public');
            $stored[] = [
                'index' => $i,
                'path' => $path,
                'url' => Storage::url($path),
                'name' => $file->getClientOriginalName() ?: "image-{$i}",
            ];
        }

        $paths = array_column($stored, 'path');
        $result = $this->clustering->cluster($paths);

        return [
            'images' => $stored,
            'global_analysis' => (string) ($result['globalAnalysis'] ?? ''),
            'global_rules' => array_values($result['globalRules'] ?? []),
            'clusters' => array_values($result['clusters'] ?? []),
        ];
    }

    /**
     * Score clusters for a caption and pick one (keyword + optional model fallback).
     *
     * @param  list<array>  $clusters  ClusteringService cluster entries
     * @return array{
     *     scores: array<int, float>,
     *     selected_index: int,
     *     selected_image_indices: list<int>,
     *     used_model_fallback: bool,
     *     top_score: float,
     *     second_score: float
     * }
     */
    public function matchCaption(array $clusters, string $caption): array
    {
        return $this->captionMatcher->match($clusters, $caption);
    }

    /**
     * Match many captions to clusters.
     *
     * @param  list<array>  $clusters
     * @param  list<string>  $captions
     * @return list<array{
     *     row_index: int,
     *     caption: string,
     *     scores: array<int, float>,
     *     selected_index: int,
     *     selected_image_indices: list<int>,
     *     used_model_fallback: bool,
     *     top_score: float,
     *     second_score: float
     * }>
     */
    public function matchCaptions(array $clusters, array $captions): array
    {
        $rows = [];
        foreach (array_values($captions) as $i => $caption) {
            $caption = trim((string) $caption);
            if ($caption === '') {
                continue;
            }
            $match = $this->matchCaption($clusters, $caption);
            $rows[] = [
                'row_index' => $i,
                'caption' => $caption,
                ...$match,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('No captions provided.');
        }

        return $rows;
    }

    /**
     * Build master prompt + generate image for one caption/refs pair.
     *
     * @param  string[]  $referencePaths
     * @return array{
     *     master_prompt: string,
     *     slots_detected: array,
     *     copy: array,
     *     visual_lock_summary: string,
     *     build_model_used: string,
     *     reference_paths: string[],
     *     reference_urls: string[],
     *     url: string,
     *     path: string,
     *     model_used: string,
     *     generation_ms: int
     * }
     */
    public function runRow(array $referencePaths, string $caption, string $aspectRatio = '1:1'): array
    {
        $built = $this->buildMasterPrompt($referencePaths, $caption, $aspectRatio);
        $generated = $this->generateImage(
            $built['reference_paths'],
            $built['master_prompt'],
            $aspectRatio,
        );

        return [
            'master_prompt' => $built['master_prompt'],
            'slots_detected' => $built['slots_detected'],
            'copy' => $built['copy'],
            'visual_lock_summary' => $built['visual_lock_summary'],
            'build_model_used' => $built['model_used'],
            'reference_paths' => $built['reference_paths'],
            'reference_urls' => $built['reference_urls'],
            'url' => $generated['url'],
            'path' => $generated['path'],
            'model_used' => $generated['model_used'],
            'generation_ms' => $generated['generation_ms'],
        ];
    }

    /**
     * @param  string[]  $referencePaths
     * @return array{slots_detected: array, copy: array, visual_lock_summary: string, master_prompt: string, model_used: string, reference_paths: string[], reference_urls: string[]}
     */
    public function buildMasterPrompt(array $referencePaths, string $caption, string $aspectRatio = '1:1'): array
    {
        $paths = $this->assertLabPaths($referencePaths);
        $built = $this->masterPromptLab->buildFromStoragePaths($paths, $caption, $aspectRatio);

        return [
            ...$built,
            'reference_paths' => $paths,
            'reference_urls' => array_map(fn (string $p) => Storage::url($p), $paths),
        ];
    }

    /**
     * @param  string[]  $referencePaths
     * @return array{url: string, path: string, model_used: string, generation_ms: int}
     */
    public function generateImage(array $referencePaths, string $masterPrompt, string $aspectRatio = '1:1'): array
    {
        $paths = $this->assertLabPaths($referencePaths);

        return $this->masterPromptLab->generateImage($paths, $masterPrompt, $aspectRatio);
    }

    /**
     * @param  string[]  $paths
     * @return list<string>
     */
    public function assertLabPaths(array $paths): array
    {
        $clean = [];
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Invalid reference path.');
            }
            if (str_contains($path, '..') || ! str_starts_with($path, self::STORAGE_PREFIX.'/')) {
                throw new RuntimeException('Invalid reference path.');
            }
            if (! Storage::disk('public')->exists($path)) {
                throw new RuntimeException("Reference file missing: {$path}");
            }
            $clean[] = $path;
        }

        if (count($clean) < 3) {
            throw new RuntimeException('Exactly 3 reference paths are required.');
        }

        return array_slice($clean, 0, 3);
    }
}
