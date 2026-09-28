<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code',50); $table->string('name'); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['station_id','code']);
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code',50); $table->string('name'); $table->timestamps();
            $table->unique(['station_id','code']);
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_no',50); $table->string('name'); $table->string('phone',30)->nullable();
            $table->string('email',150)->nullable(); $table->date('joined_on')->nullable(); $table->date('left_on')->nullable();
            $table->enum('status',['active','inactive','terminated'])->default('active'); $table->timestamps();
            $table->unique(['station_id','employee_no']); $table->index(['station_id','status']);
        });
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on'); $table->date('ends_on')->nullable(); $table->decimal('base_salary',20,4)->default(0);
            $table->string('pay_frequency',30)->default('monthly'); $table->string('contract_type',30)->default('permanent');
            $table->enum('status',['draft','active','expired','terminated'])->default('draft'); $table->timestamps();
        });
        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name',120); $table->string('account_name',120)->nullable(); $table->string('account_number',120);
            $table->string('iban',120)->nullable(); $table->boolean('is_primary')->default(false); $table->timestamps();
        });
        Schema::create('shifts', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('code',50); $table->string('name'); $table->time('starts_at'); $table->time('ends_at');
            $table->unsignedInteger('break_minutes')->default(0); $table->timestamps(); $table->unique(['station_id','code']);
        });
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->date('starts_on'); $table->date('ends_on')->nullable(); $table->timestamps();
        });
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date'); $table->dateTime('check_in')->nullable(); $table->dateTime('check_out')->nullable();
            $table->decimal('worked_hours',20,4)->default(0); $table->enum('status',['present','absent','late','leave','holiday'])->default('present');
            $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['station_id','employee_id','attendance_date']);
        });
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id(); $table->foreignId('attendance_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('hours_delta',20,4)->default(0); $table->string('reason'); $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->string('code',50); $table->string('name');
            $table->boolean('is_paid')->default(true); $table->decimal('annual_days',20,4)->default(0); $table->timestamps();
            $table->unique(['station_id','code']);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete(); $table->date('starts_on'); $table->date('ends_on');
            $table->decimal('days',20,4); $table->enum('status',['draft','pending','approved','rejected','cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date'); $table->decimal('hours',20,4); $table->decimal('rate_multiplier',20,4)->default(1);
            $table->decimal('amount',20,4)->default(0); $table->enum('status',['pending','approved','rejected','paid'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id(); $table->uuid('transaction_uuid')->unique(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); $table->date('advance_date');
            $table->decimal('amount',20,4); $table->decimal('balance',20,4); $table->enum('status',['open','settled','cancelled'])->default('open');
            $table->text('reason')->nullable(); $table->timestamps();
        });
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->string('code',50); $table->string('name');
            $table->enum('type',['earning','deduction'])->default('earning'); $table->boolean('is_taxable')->default(false); $table->timestamps();
            $table->unique(['station_id','code']);
        });
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id(); $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount',20,4)->default(0); $table->date('effective_from'); $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->string('code',50);
            $table->date('starts_on'); $table->date('ends_on'); $table->enum('status',['open','processed','closed','cancelled'])->default('open'); $table->timestamps();
            $table->unique(['station_id','code']);
        });
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id(); $table->foreignId('station_id')->constrained()->restrictOnDelete(); $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->dateTime('run_at'); $table->enum('status',['draft','processing','completed','cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['station_id','payroll_period_id']);
        });
        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id(); $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete(); $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('slip_number',80); $table->decimal('gross_amount',20,4)->default(0); $table->decimal('deduction_amount',20,4)->default(0);
            $table->decimal('net_amount',20,4)->default(0); $table->enum('status',['draft','approved','paid','voided'])->default('draft'); $table->timestamps();
            $table->unique(['payroll_run_id','employee_id']); $table->unique('slip_number');
        });
        Schema::create('payroll_slip_lines', function (Blueprint $table) {
            $table->id(); $table->foreignId('payroll_slip_id')->constrained()->cascadeOnDelete(); $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description'); $table->enum('line_type',['earning','deduction']); $table->decimal('amount',20,4); $table->timestamps();
        });
        Schema::create('advance_repayments', function (Blueprint $table) {
            $table->id(); $table->foreignId('employee_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_slip_id')->nullable()->constrained()->nullOnDelete(); $table->date('repayment_date');
            $table->decimal('amount',20,4); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id(); $table->uuid('transaction_uuid')->unique(); $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_slip_id')->constrained()->restrictOnDelete(); $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at'); $table->decimal('amount',20,4); $table->enum('method',['cash','bank','transfer','other'])->default('bank');
            $table->enum('status',['posted','voided'])->default('posted'); $table->timestamps(); $table->unique(['station_id','payroll_slip_id']);
        });
    }

    public function down(): void
    {
        foreach (['payroll_payments','advance_repayments','payroll_slip_lines','payroll_slips','payroll_runs','payroll_periods','employee_salary_components','salary_components','employee_advances','overtime_records','leave_requests','leave_types','attendance_adjustments','attendance_logs','shift_assignments','shifts','employee_bank_accounts','employee_contracts','employees','positions','departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
