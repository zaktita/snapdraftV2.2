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
        // Plans Table
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->nullable(); // stripe, paypal, etc
            $table->string('provider_product_id')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('billing_cycle')->default('monthly'); // monthly, annual, one-time
            $table->json('capabilities')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            
            $table->boolean('has_trial')->default(false);
            $table->integer('trial_days')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
        // Subscriptions Table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->string('plan_id');
            
            // Subscription Details
            $table->string('status'); // active, cancelled, expired, pending, past_due
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            
            // Billing
            $table->string('billing_cycle'); // monthly, annual, one-time
            $table->decimal('amount_paid', 10, 2);
            $table->string('currency')->default('USD');
            
            // Payment Provider
            $table->string('provider')->nullable(); // stripe, paypal, etc
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('provider_customer_id')->nullable();
            
            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Renewal
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('next_billing_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('next_billing_at');
        });
        // Subscription Usage Table
        Schema::create('subscriptions_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->string('subscription_id')->unique(); // Internal subscription ID
            $table->integer('capabilities_used')->default(0);
            $table->integer('capabilities_remaining')->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });
        // Transactions Table
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            
            // Transaction Details
            $table->string('transaction_id')->unique(); // Internal transaction ID
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider')->nullable(); // stripe, paypal, etc
            
            // Financial Details
            $table->string('type'); // payment, refund, credit, etc
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('status'); // completed, pending, failed, refunded
            
            // Payment Method
            $table->string('payment_method')->nullable(); // credit_card, paypal, etc
            $table->string('payment_method_details')->nullable();
            
            // Billing Information
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_zip')->nullable();
            
            // Metadata
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions_usage');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};