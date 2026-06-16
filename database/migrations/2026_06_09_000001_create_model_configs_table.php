<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_configs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('provider')->default('openrouter');
            $table->string('openrouter_model_id');
            $table->string('capability');
            $table->string('driver');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('config_json')->nullable();
            $table->json('cost_hint_json')->nullable();
            $table->timestamps();

            $table->index(['capability', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_configs');
    }
};
