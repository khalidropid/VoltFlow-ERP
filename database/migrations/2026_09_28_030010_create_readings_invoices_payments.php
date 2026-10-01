<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->constrained()->restrictOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reading_at');
            $table->decimal('reading_value', 20, 4);
            $table->decimal('previous_reading_value', 20, 4)->nullable();
            $table->decimal('consumption', 20, 4)->nullable();
            $table->enum('source', ['manual', 'mobile', 'imported'])->default('manual');
            $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['meter_id', 'reading_at']);
            $table->index(['station_id', 'reading_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('previous_reading', 20, 4)->default(0);
            $table->decimal('current_reading', 20, 4)->default(0);
            $table->decimal('consumption', 20, 4)->default(0);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount', 20, 4)->default(0);
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->enum('status', ['draft', 'issued', 'partially_paid', 'paid', 'void'])->default('draft')->index();
            $table->timestamps();
            $table->unique(['station_id', 'number']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_rate', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->string('description');
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['invoice_id', 'line_no']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number', 50);
            $table->dateTime('paid_at');
            $table->decimal('amount', 20, 4);
            $table->enum('method', ['cash', 'bank', 'transfer', 'other'])->default('cash');
            $table->enum('status', ['posted', 'voided'])->default('posted')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'receipt_number']);
            $table->index(['customer_id', 'paid_at']);
            $table->index(['collector_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('meter_readings');
    }
};
