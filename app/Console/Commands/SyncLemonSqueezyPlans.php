<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncLemonSqueezyPlans extends Command
{
    protected $signature = 'lemonsqueezy:sync-plans {--dry-run : Preview changes without saving}';

    protected $description = 'Sync products and variants from Lemon Squeezy into the local plans table';

    private string $apiKey;
    private string $storeId;
    private string $baseUrl = 'https://api.lemonsqueezy.com/v1';

    public function handle(): int
    {
        $this->apiKey = config('services.lemonsqueezy.api_key');
        $this->storeId = config('services.lemonsqueezy.store_id');

        if (empty($this->apiKey) || empty($this->storeId)) {
            $this->error('Missing LEMON_SQUEEZY_API_KEY or LEMON_SQUEEZY_STORE_ID in .env');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be saved.');
        }

        $this->info('Fetching products from Lemon Squeezy (store: ' . $this->storeId . ')...');

        $products = $this->fetchAllProducts();

        if (empty($products)) {
            $this->warn('No products found in your Lemon Squeezy store.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($products) . ' product(s). Fetching variants...');

        $created = 0;
        $updated = 0;

        foreach ($products as $product) {
            $productId   = $product['id'];
            $productName = $product['attributes']['name'] ?? 'Unnamed Product';
            $description = $product['attributes']['description'] ?? null;
            $price       = $product['attributes']['price'] ?? 0;
            $currency    = strtoupper($product['attributes']['price_formatted'] ? 'USD' : 'USD');

            $this->line('');
            $this->info("Product: [{$productId}] {$productName}");

            $variants = $this->fetchVariantsForProduct($productId);

            if (empty($variants)) {
                $this->warn("  → No variants found, skipping.");
                continue;
            }

            // Group variants by billing interval
            $monthly = null;
            $yearly  = null;
            $oneTime = null;

            foreach ($variants as $variant) {
                $interval = $variant['attributes']['interval'] ?? null;
                $intervalCount = $variant['attributes']['interval_count'] ?? 1;

                if ($interval === 'month' && $intervalCount == 1) {
                    $monthly = $variant;
                } elseif ($interval === 'year' && $intervalCount == 1) {
                    $yearly = $variant;
                } elseif ($interval === null) {
                    $oneTime = $variant;
                }

                $variantStatus = $variant['attributes']['status'] ?? 'unknown';
                $variantPrice  = isset($variant['attributes']['price'])
                    ? number_format($variant['attributes']['price'] / 100, 2)
                    : '?';
                $this->line("  → Variant [{$variant['id']}] interval={$interval} price=\${$variantPrice} status={$variantStatus}");
            }

            // Determine the primary variant to use as the plan base
            $primaryVariant = $monthly ?? $yearly ?? $oneTime ?? $variants[0];
            $billingCycle   = match($primaryVariant['attributes']['interval'] ?? null) {
                'year'  => 'yearly',
                'month' => 'monthly',
                default => 'one_time',
            };

            $slug = Str::slug($productName);

            // Build capabilities JSON based on product metadata / name
            $capabilities = $this->inferCapabilities($productName, $primaryVariant);

            // Price in dollars (LS stores in cents)
            $planPrice = isset($primaryVariant['attributes']['price'])
                ? $primaryVariant['attributes']['price'] / 100
                : ($price / 100);

            $planData = [
                'provider'                => 'lemonsqueezy',
                'provider_product_id'     => $primaryVariant['id'],
                'provider_variant_monthly' => $monthly ? $monthly['id'] : null,
                'provider_variant_yearly'  => $yearly  ? $yearly['id']  : null,
                'name'                    => $productName,
                'slug'                    => $slug,
                'description'             => strip_tags($description ?? ''),
                'price'                   => $planPrice,
                'currency'                => 'USD',
                'billing_cycle'           => $billingCycle,
                'capabilities'            => $capabilities,
                'is_active'               => ($primaryVariant['attributes']['status'] ?? '') === 'published',
                'is_featured'             => false,
                'sort_order'              => 0,
                'has_trial'               => false,
                'trial_days'              => null,
            ];

            if ($dryRun) {
                $this->table(array_keys($planData), [array_values($planData)]);
                continue;
            }

            $existing = Plan::withTrashed()->where('provider_product_id', $primaryVariant['id'])->first()
                ?? Plan::withTrashed()->where('slug', $slug)->first();

            if ($existing) {
                $existing->restore(); // un-soft-delete if needed
                $existing->update($planData);
                $updated++;
                $this->line("  ✓ Updated plan: {$productName}");
            } else {
                Plan::create($planData);
                $created++;
                $this->line("  ✓ Created plan: {$productName}");
            }
        }

        if (! $dryRun) {
            $this->line('');
            $this->info("Sync complete. Created: {$created}, Updated: {$updated}");
            Log::info('LemonSqueezy plans synced', ['created' => $created, 'updated' => $updated]);
        }

        return self::SUCCESS;
    }

    private function fetchAllProducts(): array
    {
        $products = [];
        $url = "{$this->baseUrl}/products?filter[store_id]={$this->storeId}&page[size]=100";

        while ($url) {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch products: ' . $response->status() . ' ' . $response->body());
                return [];
            }

            $body = $response->json();
            $products = array_merge($products, $body['data'] ?? []);
            $url = $body['links']['next'] ?? null;
        }

        return $products;
    }

    private function fetchVariantsForProduct(string $productId): array
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/variants?filter[product_id]={$productId}&page[size]=100");

        if ($response->failed()) {
            $this->error("Failed to fetch variants for product {$productId}: " . $response->status());
            return [];
        }

        return $response->json()['data'] ?? [];
    }

    private function inferCapabilities(string $productName, array $variant): array
    {
        $name = strtolower($productName);

        if (str_contains($name, 'launch') || str_contains($name, 'starter')) {
            return [
                'credits_per_month' => 100,
                'max_projects'      => 1,
                'csv_max_rows'      => 50,
                'max_team_seats'    => 1,
                'features' => [
                    'image_generation'    => true,
                    'text_generation'     => true,
                    'basic_canvas'        => true,
                    'standard_processing' => true,
                    'batch_generation'    => false,
                    'brand_dna_analysis'  => false,
                    'version_history'     => false,
                    'advanced_canvas'     => false,
                    'priority_processing' => false,
                    'batch_regeneration'  => false,
                    'team_access'         => false,
                ],
            ];
        }

        if (str_contains($name, 'growth') || str_contains($name, 'pro')) {
            return [
                'credits_per_month' => 350,
                'max_projects'      => 3,
                'csv_max_rows'      => 300,
                'max_team_seats'    => 1,
                'features' => [
                    'image_generation'    => true,
                    'text_generation'     => true,
                    'basic_canvas'        => true,
                    'standard_processing' => false,
                    'batch_generation'    => true,
                    'brand_dna_analysis'  => true,
                    'version_history'     => true,
                    'advanced_canvas'     => true,
                    'priority_processing' => true,
                    'batch_regeneration'  => false,
                    'team_access'         => false,
                ],
            ];
        }

        if (str_contains($name, 'scale') || str_contains($name, 'business') || str_contains($name, 'enterprise')) {
            return [
                'credits_per_month' => 900,
                'max_projects'      => 10,
                'csv_max_rows'      => 1500,
                'max_team_seats'    => 3,
                'features' => [
                    'image_generation'    => true,
                    'text_generation'     => true,
                    'basic_canvas'        => true,
                    'standard_processing' => false,
                    'batch_generation'    => true,
                    'brand_dna_analysis'  => true,
                    'version_history'     => true,
                    'advanced_canvas'     => true,
                    'priority_processing' => true,
                    'batch_regeneration'  => true,
                    'team_access'         => true,
                ],
            ];
        }

        // Default fallback
        return [
            'credits_per_month' => 0,
            'max_projects'      => 0,
            'csv_max_rows'      => 0,
            'max_team_seats'    => 1,
            'features'          => [],
        ];
    }
}
