<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('type', ['cash', 'bank'])->default('cash');
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('collector_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('balance', 20, 4)->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['station_id', 'collector_id']);
        });

        Schema::create('collection_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 50);
            $table->dateTime('settled_at');
            $table->decimal('amount', 20, 4);
            $table->enum('status', ['posted', 'voided'])->default('posted');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'number']);
            $table->index(['collector_account_id', 'settled_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('collector_id')->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['cash_account_id']);
            $table->dropColumn(['journal_entry_id', 'cash_account_id']);
        });
        Schema::dropIfExists('collection_settlements');
        Schema::dropIfExists('collector_accounts');
        Schema::dropIfExists('cash_accounts');
    }
};
