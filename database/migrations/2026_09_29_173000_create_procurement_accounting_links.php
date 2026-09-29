<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_account_links', function (Blueprint $table) {
            $table->foreignId('tax_account_id')
                ->nullable()
                ->after('expense_account_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('status')
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->index(['station_id', 'supplier_id', 'status', 'journal_entry_id'], 'supplier_invoice_posting_idx');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('status')
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->foreignId('reversal_journal_entry_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->dateTime('voided_at')->nullable()->after('reversal_journal_entry_id');
            $table->foreignId('voided_by')
                ->nullable()
                ->after('voided_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['station_id', 'supplier_id', 'status', 'paid_at'], 'supplier_payment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex('supplier_payment_status_idx');
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['reversal_journal_entry_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn([
                'voided_by',
                'voided_at',
                'reversal_journal_entry_id',
                'journal_entry_id',
            ]);
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropIndex('supplier_invoice_posting_idx');
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('supplier_account_links', function (Blueprint $table) {
            $table->dropForeign(['tax_account_id']);
            $table->dropColumn('tax_account_id');
        });
    }
};
