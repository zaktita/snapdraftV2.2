<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Lemon Squeezy specific fields
            $table->string('lemonsqueezy_id')->nullable()->after('plan_id');
            $table->string('lemonsqueezy_customer_id')->nullable()->after('lemonsqueezy_id');
            $table->string('lemonsqueezy_order_id')->nullable()->after('lemonsqueezy_customer_id');
            $table->string('lemonsqueezy_variant_id')->nullable()->after('lemonsqueezy_order_id');
            $table->string('lemonsqueezy_product_id')->nullable()->after('lemonsqueezy_variant_id');
            
            // Rename billing_cycle to billing_period for consistency
            $table->renameColumn('billing_cycle', 'billing_period');
            
            // Add price field
            $table->decimal('price', 10, 2)->nullable()->after('amount_paid');
            
            // Add renews_at timestamp
            $table->timestamp('renews_at')->nullable()->after('next_billing_at');
            
            // Add paused_at for Lemon Squeezy pause feature
            $table->timestamp('paused_at')->nullable()->after('renews_at');
            
            // Payment method details for display
            $table->string('card_brand')->nullable()->after('paused_at');
            $table->string('card_last_four')->nullable()->after('card_brand');
            
            // Lemon Squeezy portal URLs
            $table->text('update_payment_url')->nullable()->after('card_last_four');
            $table->text('customer_portal_url')->nullable()->after('update_payment_url');
            
            // Capabilities tracking as JSON
            $table->json('capabilities')->nullable()->after('customer_portal_url');
            
            // Metadata for any additional provider-specific data
            $table->json('metadata')->nullable()->after('capabilities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'lemonsqueezy_id',
                'lemonsqueezy_customer_id',
                'lemonsqueezy_order_id',
                'lemonsqueezy_variant_id',
                'lemonsqueezy_product_id',
                'price',
                'renews_at',
                'paused_at',
                'card_brand',
                'card_last_four',
                'update_payment_url',
                'customer_portal_url',
                'capabilities',
                'metadata',
            ]);
            
            $table->renameColumn('billing_period', 'billing_cycle');
        });
    }
};
