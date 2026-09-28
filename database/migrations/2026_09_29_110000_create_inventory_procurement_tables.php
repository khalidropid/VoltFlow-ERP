<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('symbol', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('item_type', 30)->default('stock');
            $table->decimal('standard_cost', 20, 4)->default(0);
            $table->decimal('reorder_level', 20, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('type', ['main', 'fuel', 'spares', 'other'])->default('main');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('average_cost', 20, 4)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->enum('movement_type', ['receipt', 'issue', 'transfer_in', 'transfer_out', 'adjustment'])->index();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4)->default(0);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('moved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->unique(['station_id', 'code']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 50);
            $table->date('request_date');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->text('description')->nullable();
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['purchase_request_id', 'line_no']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->date('order_date');
            $table->enum('status', ['draft', 'approved', 'sent', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->decimal('total', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_no']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->date('receipt_date');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['goods_receipt_id', 'line_no']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 80);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->enum('status', ['draft', 'posted', 'partially_paid', 'paid', 'voided'])->default('draft');
            $table->timestamps();
            $table->unique(['station_id', 'number']);
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->unsignedInteger('line_no');
            $table->timestamps();
            $table->unique(['supplier_invoice_id', 'line_no']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number', 80);
            $table->dateTime('paid_at');
            $table->decimal('amount', 20, 4);
            $table->enum('method', ['cash', 'bank', 'transfer', 'other'])->default('cash');
            $table->enum('status', ['posted', 'voided'])->default('posted');
            $table->timestamps();
            $table->unique(['station_id', 'receipt_number']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->timestamps();
            $table->unique(['supplier_payment_id', 'supplier_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('items');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('item_categories');
    }
};
