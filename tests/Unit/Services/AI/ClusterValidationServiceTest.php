<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\ClusterValidationService;
use Tests\TestCase;

class ClusterValidationServiceTest extends TestCase
{
    private ClusterValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClusterValidationService();
    }

    /** @test */
    public function valid_clusters_pass_all_checks(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1, 2]],
            ['cluster_id' => 1, 'image_indices' => [3, 4]],
        ];

        $result = $this->service->validateStyleClusters($clusters, 5);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function duplicate_image_assignment_fails_validation(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1, 2]],
            ['cluster_id' => 1, 'image_indices' => [2, 3]],  // index 2 duplicated
        ];

        $result = $this->service->validateStyleClusters($clusters, 4);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('2', $result['errors'][0]);
    }

    /** @test */
    public function out_of_bounds_image_index_fails_validation(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1, 99]],  // 99 is out of bounds
        ];

        $result = $this->service->validateStyleClusters($clusters, 3);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function single_image_cluster_produces_error_when_size_enforced(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0]],  // only 1 image — too small
            ['cluster_id' => 1, 'image_indices' => [1, 2]],
        ];

        $result = $this->service->validateStyleClusters($clusters, 3, enforceClusterSize: true);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('expected 2 or 3', $result['errors'][0]);
    }

    /** @test */
    public function cluster_size_is_not_enforced_when_flag_is_false(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0]],
            ['cluster_id' => 1, 'image_indices' => [1, 2]],
        ];

        $result = $this->service->validateStyleClusters($clusters, 3, enforceClusterSize: false);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function unassigned_images_create_warning_when_full_coverage_required(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1]],
            // images 2 and 3 are not in any cluster
        ];

        $result = $this->service->validateStyleClusters($clusters, 4, requireFullCoverage: true);

        $this->assertNotEmpty($result['warnings']);
        $coverageWarnings = array_filter($result['warnings'], fn($w) => str_contains($w, '2'));
        $this->assertNotEmpty($coverageWarnings);
    }

    /** @test */
    public function unassigned_images_not_warned_when_full_coverage_not_required(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1]],
        ];

        $result = $this->service->validateStyleClusters($clusters, 4, requireFullCoverage: false);

        $coverageWarnings = array_filter($result['warnings'], fn($w) => str_contains($w, 'not assigned'));
        $this->assertEmpty($coverageWarnings);
    }

    /** @test */
    public function empty_clusters_array_is_valid_with_no_coverage_required(): void
    {
        $result = $this->service->validateStyleClusters([], 0, requireFullCoverage: false);

        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function oversized_cluster_produces_error_when_size_enforced(): void
    {
        $clusters = [
            ['cluster_id' => 0, 'image_indices' => [0, 1, 2, 3]],  // 4 images — too large
        ];

        $result = $this->service->validateStyleClusters($clusters, 4, enforceClusterSize: true);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function accepts_camel_case_image_indices_key(): void
    {
        // Service supports both 'image_indices' (snake_case) and 'imageIndices' (camelCase)
        $clusters = [
            ['cluster_id' => 0, 'imageIndices' => [0, 1, 2]],
            ['cluster_id' => 1, 'imageIndices' => [3, 4]],
        ];

        $result = $this->service->validateStyleClusters($clusters, 5);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
}
