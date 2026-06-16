<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_generate_sessions', function (Blueprint $table) {
            $table->json('prompt_json')->nullable()->after('final_prompt');
            $table->text('compiled_prompt')->nullable()->after('prompt_json');
            $table->string('cluster_key')->nullable()->after('compiled_prompt');
            $table->string('cluster_label')->nullable()->after('cluster_key');
            $table->json('selected_cluster_images')->nullable()->after('cluster_label');
        });
    }

    public function down(): void
    {
        Schema::table('quick_generate_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'prompt_json',
                'compiled_prompt',
                'cluster_key',
                'cluster_label',
                'selected_cluster_images',
            ]);
        });
    }
};
