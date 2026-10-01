<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fuel_types', function(Blueprint $t){
            $t->id(); $t->foreignId('station_id')->nullable()->constrained()->nullOnDelete(); $t->string('code',50); $t->string('name');
            $t->string('unit',20)->default('liter'); $t->decimal('density',20,4)->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['station_id','code']);
        });
        Schema::create('fuel_tanks', function(Blueprint $t){
            $t->id(); $t->foreignId('station_id')->constrained()->restrictOnDelete(); $t->string('code',50); $t->string('name');
            $t->decimal('capacity',20,4)->default(0); $t->decimal('current_quantity',20,4)->default(0); $t->boolean('is_active')->default(true);
            $t->timestamps(); $t->unique(['station_id','code']);
        });
        Schema::create('fuel_receipts', function(Blueprint $t){
            $t->id(); $t->uuid('transaction_uuid')->unique(); $t->foreignId('station_id')->constrained()->restrictOnDelete();
            $t->foreignId('fuel_type_id')->constrained()->restrictOnDelete(); $t->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $t->dateTime('received_at'); $t->decimal('quantity',20,4); $t->decimal('unit_cost',20,4)->default(0); $t->decimal('total_cost',20,4)->default(0);
            $t->string('supplier_reference')->nullable(); $t->text('notes')->nullable(); $t->enum('status',['draft','posted','voided'])->default('draft'); $t->timestamps();
        });
        Schema::create('fuel_issues', function(Blueprint $t){
            $t->id(); $t->uuid('transaction_uuid')->unique(); $t->foreignId('station_id')->constrained()->restrictOnDelete();
            $t->foreignId('fuel_type_id')->constrained()->restrictOnDelete(); $t->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $t->foreignId('generator_id')->nullable()->constrained()->nullOnDelete(); $t->dateTime('issued_at'); $t->decimal('quantity',20,4);
            $t->decimal('unit_cost',20,4)->default(0); $t->decimal('total_cost',20,4)->default(0); $t->enum('status',['draft','posted','voided'])->default('draft');
            $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('fuel_adjustments', function(Blueprint $t){
            $t->id(); $t->uuid('transaction_uuid')->unique(); $t->foreignId('station_id')->constrained()->restrictOnDelete();
            $t->foreignId('fuel_type_id')->constrained()->restrictOnDelete(); $t->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $t->dateTime('adjusted_at'); $t->decimal('quantity_delta',20,4); $t->string('reason'); $t->enum('status',['draft','posted','voided'])->default('draft'); $t->timestamps();
        });
        Schema::create('fuel_stock_movements', function(Blueprint $t){
            $t->id(); $t->uuid('transaction_uuid')->unique(); $t->foreignId('station_id')->constrained()->restrictOnDelete();
            $t->foreignId('fuel_type_id')->constrained()->restrictOnDelete(); $t->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $t->enum('movement_type',['receipt','issue','adjustment'])->index(); $t->decimal('quantity',20,4); $t->decimal('unit_cost',20,4)->default(0);
            $t->string('reference_type',100)->nullable(); $t->unsignedBigInteger('reference_id')->nullable(); $t->dateTime('moved_at'); $t->timestamps();
        });
        Schema::create('asset_categories', function(Blueprint $t){
            $t->id(); $t->foreignId('station_id')->nullable()->constrained()->nullOnDelete(); $t->string('code',50); $t->string('name'); $t->timestamps();
            $t->unique(['station_id','code']);
        });
        Schema::create('assets', function(Blueprint $t){
            $t->id(); $t->foreignId('station_id')->constrained()->restrictOnDelete(); $t->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code',80); $t->string('name'); $t->string('serial_number')->nullable(); $t->date('acquired_on')->nullable();
            $t->decimal('purchase_cost',20,4)->default(0); $t->decimal('residual_value',20,4)->default(0); $t->unsignedInteger('useful_life_months')->nullable();
            $t->date('depreciation_start_on')->nullable(); $t->enum('status',['active','maintenance','disposed','retired'])->default('active'); $t->timestamps();
            $t->unique(['station_id','code']);
        });
        Schema::create('maintenance_plans', function(Blueprint $t){
            $t->id(); $t->foreignId('station_id')->constrained()->restrictOnDelete(); $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->string('name'); $t->unsignedInteger('interval_days')->nullable(); $t->decimal('interval_hours',20,4)->nullable(); $t->date('next_due_on')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('maintenance_work_orders', function(Blueprint $t){
            $t->id(); $t->uuid('transaction_uuid')->unique(); $t->foreignId('station_id')->constrained()->restrictOnDelete(); $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->string('number',50); $t->string('title'); $t->enum('type',['preventive','corrective','inspection','other'])->default('corrective');
            $t->enum('priority',['low','normal','high','critical'])->default('normal'); $t->enum('status',['draft','open','in_progress','completed','cancelled'])->default('draft');
            $t->date('opened_on'); $t->date('completed_on')->nullable(); $t->text('problem_description')->nullable(); $t->text('resolution')->nullable(); $t->timestamps();
            $t->unique(['station_id','number']);
        });
        Schema::create('maintenance_work_order_parts', function(Blueprint $t){
            $t->id(); $t->foreignId('maintenance_work_order_id')->constrained()->cascadeOnDelete(); $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity',20,4); $t->decimal('unit_cost',20,4)->default(0); $t->decimal('amount',20,4)->default(0); $t->timestamps();
        });
        Schema::create('maintenance_work_order_labor', function(Blueprint $t){
            $t->id(); $t->foreignId('maintenance_work_order_id')->constrained()->cascadeOnDelete(); $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->decimal('hours',20,4); $t->decimal('rate',20,4)->default(0); $t->decimal('amount',20,4)->default(0); $t->timestamps();
        });
        Schema::create('maintenance_work_order_expenses', function(Blueprint $t){
            $t->id(); $t->foreignId('maintenance_work_order_id')->constrained('maintenance_work_orders', 'id', 'mwo_expenses_work_order_fk')->cascadeOnDelete(); $t->string('description'); $t->decimal('amount',20,4); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['maintenance_work_order_expenses','maintenance_work_order_labor','maintenance_work_order_parts','maintenance_work_orders','maintenance_plans','assets','asset_categories','fuel_stock_movements','fuel_adjustments','fuel_issues','fuel_receipts','fuel_tanks','fuel_types'] as $table) Schema::dropIfExists($table);
    }
};
