<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('role', 64);
            $table->string('monthly_post_volume', 32);
            $table->text('visual_workflow');
            $table->string('status', 32)->default('pending');
            $table->string('invite_code', 20)->nullable();
            $table->foreignUuid('beta_invite_id')->nullable()->constrained('beta_invites')->nullOnDelete();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_applications');
    }
};
