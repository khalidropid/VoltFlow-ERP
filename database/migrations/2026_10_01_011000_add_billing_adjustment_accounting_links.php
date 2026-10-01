<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_adjustments', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->index(['invoice_id', 'journal_entry_id']);
        });
        foreach (['credit_notes','debit_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->index(['station_id', 'journal_entry_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['credit_notes','debit_notes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropIndex(['station_id', 'journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            });
        }
        Schema::table('invoice_adjustments', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropIndex(['invoice_id', 'journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });
    }
};