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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('name')->nullable()->after('title'); // Alternative to title for some wizards
            $table->string('format')->nullable()->after('description'); // square, landscape, portrait, story
            $table->string('status')->default('draft')->after('format'); // draft, processing, completed, failed
            $table->softDeletes(); // Add soft deletes for better data retention
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['name', 'format', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
