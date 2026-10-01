<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('phone', 30)->nullable()->index();
            $table->string('address')->nullable();
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active')->index();
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 100);
            $table->string('meter_type', 50)->default('energy');
            $table->decimal('multiplier', 12, 4)->default(1);
            $table->decimal('initial_reading', 20, 4)->default(0);
            $table->date('installed_at')->nullable();
            $table->enum('status', ['active', 'removed', 'faulty'])->default('active')->index();
            $table->timestamps();
            $table->unique(['station_id', 'serial_number']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('tariff_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_unit', 20, 4);
            $table->decimal('to_unit', 20, 4)->nullable();
            $table->decimal('rate', 20, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tariff_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_slabs');
        Schema::dropIfExists('tariffs');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('customers');
    }
};
