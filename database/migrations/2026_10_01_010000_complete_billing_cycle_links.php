<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('billing_cycle_id')->nullable()->after('tariff_id')->constrained('billing_cycles')->nullOnDelete();
            $table->index(['station_id', 'billing_cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['billing_cycle_id']);
            $table->dropIndex(['station_id', 'billing_cycle_id', 'status']);
            $table->dropColumn('billing_cycle_id');
        });
    }
};