<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(
                ['station_id', 'source_type', 'source_id'],
                'journal_entries_station_source_unique'
            );
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->unique(
                ['station_id', 'account_id'],
                'cash_accounts_station_gl_account_unique'
            );
        });

        Schema::table('collector_accounts', function (Blueprint $table) {
            $table->unique(
                ['station_id', 'account_id'],
                'collector_accounts_station_gl_account_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('collector_accounts', function (Blueprint $table) {
            $table->dropUnique('collector_accounts_station_gl_account_unique');
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropUnique('cash_accounts_station_gl_account_unique');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_station_source_unique');
        });
    }
};
