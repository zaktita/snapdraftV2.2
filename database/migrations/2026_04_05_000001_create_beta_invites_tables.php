<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->foreignUuid('plan_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('credits')->default(100);
            $table->unsignedInteger('duration_days')->default(14);
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('beta_invite_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('beta_invite_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['beta_invite_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_invite_redemptions');
        Schema::dropIfExists('beta_invites');
    }
};
