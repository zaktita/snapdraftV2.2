<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove deprecated subscription fields from users table.
     * All subscription data should now be in the subscriptions table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns that actually exist
            $table->dropColumn([
                'subscription_tier',
                'credits_remaining',
                'credits_total',
                'subscription_started_at',
                'subscription_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore the fields that were removed
            $table->string('subscription_tier')->default('free')->after('is_admin');
            $table->integer('credits_remaining')->default(10)->after('subscription_tier');
            $table->integer('credits_total')->default(10)->after('credits_remaining');
            $table->timestamp('subscription_started_at')->nullable()->after('credits_total');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->string('stripe_customer_id')->nullable()->after('subscription_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
        });
    }
};
