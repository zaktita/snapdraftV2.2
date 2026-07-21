<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('lemonsqueezy_invoice_id')->nullable()->unique()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['lemonsqueezy_invoice_id']);
            $table->dropColumn('lemonsqueezy_invoice_id');
        });
    }
};
