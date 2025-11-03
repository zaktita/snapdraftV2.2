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
        Schema::create('generation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ai_model'); // 'gemini-pro', 'gpt-4', etc.
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost', 10, 4)->default(0); // Track costs
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('request_data')->nullable(); // Store the request params
            $table->json('response_data')->nullable(); // Store the response
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('project_id');
            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_history');
    }
};
