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
        Schema::table('generation_history', function (Blueprint $table) {
            $table->text('prompt')->nullable()->after('ai_model');
            $table->foreignId('image_id')->nullable()->after('project_id')->constrained()->onDelete('set null');
            $table->json('parameters')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generation_history', function (Blueprint $table) {
            $table->dropForeign(['image_id']);
            $table->dropColumn(['prompt', 'image_id', 'parameters']);
        });
    }
};
