<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('timezone', 64)->default('Asia/Aden');
            $table->string('currency_code', 10)->default('YER');
            $table->string('currency_symbol', 10)->nullable();
            $table->string('date_format', 32)->default('Y-m-d');
            $table->unsignedInteger('invoice_due_days')->default(0);
            $table->timestamps();
            $table->unique('station_id');
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('prefix', 20)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('padding')->default(6);
            $table->timestamps();
            $table->unique(['station_id', 'document_type']);
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
            $table->index(['station_id', 'service_area_id']);
        });

        Schema::create('collector_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('street_id')->nullable()->constrained()->nullOnDelete();
            $table->date('active_from');
            $table->date('active_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['station_id', 'collector_id', 'is_active']);
            $table->index(['station_id', 'service_area_id', 'is_active']);
            $table->index(['station_id', 'street_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_assignments');
        Schema::dropIfExists('streets');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('station_settings');
    }
};
