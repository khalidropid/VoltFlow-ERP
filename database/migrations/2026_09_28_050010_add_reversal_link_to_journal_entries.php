<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reversal_of_journal_entry_id')
                ->nullable()
                ->after('source_id')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_journal_entry_id']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn('reversal_of_journal_entry_id');
        });
    }
};
