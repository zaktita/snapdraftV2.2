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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // Store wizard settings, AI parameters, etc.
            $table->boolean('is_favorite')->default(false);
            $table->string('featured_image')->nullable(); // Path to thumbnail/featured image
            $table->integer('images_count')->default(0); // Cached count for performance
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('user_id');
            $table->index('is_favorite');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
