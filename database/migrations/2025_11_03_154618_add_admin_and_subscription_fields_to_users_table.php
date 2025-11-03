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
        Schema::table('users', function (Blueprint $table) {
            // Admin flag
            $table->boolean('is_admin')->default(false)->after('email_verified_at');
            
            // Subscription fields
            $table->string('subscription_tier')->default('free')->after('is_admin'); // free, pro, enterprise
            $table->integer('credits_remaining')->default(10)->after('subscription_tier'); // Monthly AI generation credits
            $table->integer('credits_total')->default(10)->after('credits_remaining'); // Total credits for current tier
            $table->timestamp('subscription_started_at')->nullable()->after('credits_total');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->string('stripe_customer_id')->nullable()->after('subscription_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            
            // Usage tracking
            $table->integer('total_generations')->default(0)->after('stripe_subscription_id');
            $table->timestamp('last_generation_at')->nullable()->after('total_generations');
            
            // Account status
            $table->boolean('is_suspended')->default(false)->after('last_generation_at');
            $table->text('suspension_reason')->nullable()->after('is_suspended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'subscription_tier',
                'credits_remaining',
                'credits_total',
                'subscription_started_at',
                'subscription_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id',
                'total_generations',
                'last_generation_at',
                'is_suspended',
                'suspension_reason',
            ]);
        });
    }
};
