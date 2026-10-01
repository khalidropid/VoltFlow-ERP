<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('is_taxable')
                ->constrained('chart_of_accounts')->nullOnDelete();
            $table->index(['station_id', 'type', 'account_id'], 'salary_components_accounting_idx');
        });

        Schema::table('employee_account_links', function (Blueprint $table) {
            $table->foreignId('employee_advance_account_id')->nullable()->after('salary_expense_account_id')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('overtime_records', function (Blueprint $table) {
            $table->foreignId('payroll_slip_id')->nullable()->after('amount')
                ->constrained('payroll_slips')->nullOnDelete();
            $table->index(['station_id', 'employee_id', 'status', 'payroll_slip_id'], 'overtime_payroll_idx');
        });

        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('net_amount')
                ->constrained('journal_entries')->nullOnDelete();
        });

        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('cash_account_id')
                ->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')
                ->constrained('journal_entries')->nullOnDelete();
            $table->dateTime('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['station_id', 'status', 'paid_at'], 'payroll_payments_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropIndex('payroll_payments_status_idx');
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['reversal_journal_entry_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['voided_by', 'voided_at', 'reversal_journal_entry_id', 'journal_entry_id']);
        });

        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('overtime_records', function (Blueprint $table) {
            $table->dropIndex('overtime_payroll_idx');
            $table->dropForeign(['payroll_slip_id']);
            $table->dropColumn('payroll_slip_id');
        });

        Schema::table('employee_account_links', function (Blueprint $table) {
            $table->dropForeign(['employee_advance_account_id']);
            $table->dropColumn('employee_advance_account_id');
        });

        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropIndex('salary_components_accounting_idx');
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
