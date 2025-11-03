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
        Schema::create('brand_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('url'); // Path to uploaded reference image
            $table->string('thumbnail_url')->nullable();
            $table->json('analysis_data')->nullable(); // AI-extracted brand style data (colors, fonts, etc.)
            $table->integer('order')->default(0);
            $table->timestamps();
            
            // Index
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_references');
    }
};
