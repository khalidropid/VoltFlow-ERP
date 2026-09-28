<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropUnique('payroll_payments_station_id_payroll_slip_id_unique');
            $table->index(['station_id', 'payroll_slip_id', 'status'], 'payroll_payment_slip_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropIndex('payroll_payment_slip_status_idx');
            $table->unique(['station_id', 'payroll_slip_id']);
        });
    }
};
