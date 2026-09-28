<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_slip_lines', function (Blueprint $table) {
            $table->string('source_type', 40)->nullable()->after('line_type');
            $table->index(['payroll_slip_id', 'line_type', 'source_type'], 'payroll_slip_lines_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_slip_lines', function (Blueprint $table) {
            $table->dropIndex('payroll_slip_lines_source_idx');
            $table->dropColumn('source_type');
        });
    }
};
