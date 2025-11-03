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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('url'); // Full-size image path
            $table->string('thumbnail_url')->nullable(); // Small thumbnail path
            $table->text('prompt')->nullable(); // The prompt used to generate this image
            $table->json('metadata')->nullable(); // AI model, dimensions, generation params, etc.
            $table->integer('order')->default(0); // For custom ordering within project
            $table->boolean('is_favorite')->default(false); // User can favorite individual images
            $table->string('format')->nullable(); // jpg, png, webp, etc.
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('project_id');
            $table->index('created_at');
            $table->index(['project_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
