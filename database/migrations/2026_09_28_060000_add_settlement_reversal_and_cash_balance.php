<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->decimal('balance', 20, 4)->default(0)->after('account_id');
        });

        Schema::table('collection_settlements', function (Blueprint $table) {
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')
                ->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('collection_settlements', function (Blueprint $table) {
            $table->dropForeign(['reversal_journal_entry_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['reversal_journal_entry_id', 'voided_by', 'voided_at', 'void_reason']);
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
