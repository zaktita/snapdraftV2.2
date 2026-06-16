<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_history', function (Blueprint $table) {
            $table->json('prompt_json')->nullable()->after('prompt');
            $table->text('compiled_prompt')->nullable()->after('prompt_json');
            $table->string('cluster_key')->nullable()->after('compiled_prompt');
            $table->boolean('json_valid')->nullable()->after('cluster_key');
        });
    }

    public function down(): void
    {
        Schema::table('generation_history', function (Blueprint $table) {
            $table->dropColumn(['prompt_json', 'compiled_prompt', 'cluster_key', 'json_valid']);
        });
    }
};
