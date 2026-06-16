<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('summary')->nullable();
            $table->json('keywords_json')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        Schema::create('project_cluster_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_reference_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_anchor')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_cluster_images');
        Schema::dropIfExists('project_clusters');
    }
};
