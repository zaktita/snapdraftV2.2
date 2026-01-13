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
        Schema::create('quick_generate_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('caption'); // Changed from string to text to allow up to 500 chars
            $table->string('format')->default('1:1'); // square, portrait, landscape, etc.
            $table->string('status')->default('pending'); // pending, analyzing, generating, completed, failed
            $table->json('brand_analysis_data')->nullable(); // Stores clusters, image analysis
            $table->string('extracted_title')->nullable(); // AI-generated title
            $table->text('extracted_description')->nullable(); // AI-generated description
            $table->integer('selected_cluster_id')->nullable();
            $table->json('selected_image_indices')->nullable(); // Array of selected reference image indices
            $table->text('final_prompt')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quick_generate_sessions');
    }
};
