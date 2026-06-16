<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('dna_json')->nullable()->after('settings');
            $table->text('dna_summary')->nullable()->after('dna_json');
            $table->timestamp('dna_extracted_at')->nullable()->after('dna_summary');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['dna_json', 'dna_summary', 'dna_extracted_at']);
        });
    }
};
