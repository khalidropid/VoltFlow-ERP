<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('capacity_kw', 20, 4)->default(0);
            $table->decimal('rated_voltage', 20, 4)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'retired'])->default('active');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
            $table->unique(['station_id', 'serial_number']);
        });

        Schema::create('generator_runtime_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('generator_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('stopped_at')->nullable();
            $table->decimal('hours', 20, 4)->nullable();
            $table->decimal('load_percent', 8, 4)->nullable();
            $table->decimal('energy_kwh', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['station_id', 'generator_id', 'started_at']);
        });

        Schema::create('generation_readings', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('generator_id')->constrained()->restrictOnDelete();
            $table->dateTime('reading_at');
            $table->decimal('energy_kwh', 20, 4);
            $table->decimal('active_power_kw', 20, 4)->nullable();
            $table->decimal('reactive_power_kvar', 20, 4)->nullable();
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('feeders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->decimal('capacity_kw', 20, 4)->default(0);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('feeder_readings', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('feeder_id')->constrained()->restrictOnDelete();
            $table->dateTime('reading_at');
            $table->decimal('energy_kwh', 20, 4);
            $table->decimal('current_amp', 20, 4)->nullable();
            $table->decimal('voltage', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feeder_id')->nullable()->constrained()->nullOnDelete();
            $table->date('connected_on');
            $table->date('disconnected_on')->nullable();
            $table->string('connection_status', 30)->default('connected');
            $table->decimal('connection_load_kw', 20, 4)->nullable();
            $table->timestamps();
            $table->index(['station_id', 'customer_id', 'connection_status'], 'cust_conn_station_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_connections');
        Schema::dropIfExists('feeder_readings');
        Schema::dropIfExists('feeders');
        Schema::dropIfExists('generation_readings');
        Schema::dropIfExists('generator_runtime_logs');
        Schema::dropIfExists('generators');
    }
};
