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

        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_journal_entry_id']);
            $table->dropColumn('reversal_of_journal_entry_id');
        });
    }
};
