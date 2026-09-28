<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('service_area_id')->nullable()->after('station_id')->constrained()->nullOnDelete();
            $table->foreignId('street_id')->nullable()->after('service_area_id')->constrained()->nullOnDelete();
            $table->string('customer_type', 50)->nullable()->after('name');
            $table->string('account_number', 100)->nullable()->after('customer_type');
            $table->text('notes')->nullable()->after('address');
            $table->index(['station_id', 'service_area_id', 'status']);
            $table->index(['station_id', 'street_id', 'status']);
            $table->unique(['station_id', 'account_number']);
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('relationship', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['customer_id', 'is_primary']);
        });

        Schema::create('customer_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type', 50);
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->dateTime('requested_at');
            $table->dateTime('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['station_id', 'status', 'requested_at']);
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->string('meter_number', 100)->nullable()->after('serial_number');
            $table->string('phase', 20)->nullable()->after('meter_type');
            $table->index(['station_id', 'meter_number']);
        });

        Schema::create('meter_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('installed_on');
            $table->date('removed_on')->nullable();
            $table->decimal('initial_reading', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['station_id', 'meter_id', 'installed_on']);
            $table->index(['station_id', 'customer_id', 'installed_on']);
        });

        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_period_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->date('reading_from');
            $table->date('reading_to');
            $table->date('billing_date')->nullable();
            $table->enum('status', ['draft', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('customer_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('tariff_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['station_id', 'customer_id', 'is_active']);
        });

        Schema::create('invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('reason')->nullable();
            $table->decimal('amount', 20, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->date('note_date');
            $table->decimal('amount', 20, 4);
            $table->string('reason')->nullable();
            $table->enum('status', ['draft', 'issued', 'void'])->default('draft');
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_rate', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['credit_note_id', 'line_no']);
        });

        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->date('note_date');
            $table->decimal('amount', 20, 4);
            $table->string('reason')->nullable();
            $table->enum('status', ['draft', 'issued', 'void'])->default('draft');
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_rate', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['debit_note_id', 'line_no']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_adjustments');
        Schema::dropIfExists('customer_tariffs');
        Schema::dropIfExists('billing_cycles');
        Schema::dropIfExists('billing_periods');
        Schema::dropIfExists('meter_installations');
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn(['meter_number', 'phase']);
        });
        Schema::dropIfExists('customer_change_requests');
        Schema::dropIfExists('customer_contacts');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'account_number']);
            $table->dropColumn(['service_area_id', 'street_id', 'customer_type', 'account_number', 'notes']);
        });
    }
};
