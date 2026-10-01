<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_settlements', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_settlements', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });
    }
};
