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
        Schema::table('images', function (Blueprint $table) {
            // Add index for favorite images filter
            $table->index('is_favorite', 'images_is_favorite_index');

            // Add composite index for project's favorite images queries
            $table->index(['project_id', 'is_favorite'], 'images_project_favorite_index');
        });

        Schema::table('projects', function (Blueprint $table) {
            // Add composite index for user's recent projects
            $table->index(['user_id', 'created_at'], 'projects_user_created_index');

            // Add composite index for user's favorite projects
            $table->index(['user_id', 'is_favorite', 'created_at'], 'projects_user_favorite_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('images_is_favorite_index');
            $table->dropIndex('images_project_favorite_index');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_user_created_index');
            $table->dropIndex('projects_user_favorite_created_index');
        });
    }
};
