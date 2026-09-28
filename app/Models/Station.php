<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Station extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'timezone',
        'currency_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function customers() { return $this->hasMany(Customer::class); }
    public function meters() { return $this->hasMany(Meter::class); }
    public function tariffs() { return $this->hasMany(Tariff::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function chartOfAccounts() { return $this->hasMany(ChartOfAccount::class); }
    public function fiscalPeriods() { return $this->hasMany(FiscalPeriod::class); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class); }
    public function cashAccounts() { return $this->hasMany(CashAccount::class); }
    public function collectorAccounts() { return $this->hasMany(CollectorAccount::class); }
    public function collectionSettlements() { return $this->hasMany(CollectionSettlement::class); }
    public function cashMovements() { return $this->hasMany(CashMovement::class); }
    public function cashClosings() { return $this->hasMany(CashClosing::class); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class); }
    public function bankReconciliations() { return $this->hasMany(BankReconciliation::class); }
    public function costCenters() { return $this->hasMany(CostCenter::class); }
    public function accountingRules() { return $this->hasMany(AccountingRule::class); }
    public function customerAccountLinks() { return $this->hasMany(CustomerAccountLink::class); }
    public function supplierAccountLinks() { return $this->hasMany(SupplierAccountLink::class); }
    public function employeeAccountLinks() { return $this->hasMany(EmployeeAccountLink::class); }
    public function generators() { return $this->hasMany(Generator::class); }
    public function feeders() { return $this->hasMany(Feeder::class); }
    public function customerConnections() { return $this->hasMany(CustomerConnection::class); }
    public function generationReadings() { return $this->hasMany(GenerationReading::class); }
    public function feederReadings() { return $this->hasMany(FeederReading::class); }
    public function itemCategories() { return $this->hasMany(ItemCategory::class); }
    public function items() { return $this->hasMany(Item::class); }
    public function warehouses() { return $this->hasMany(Warehouse::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function suppliers() { return $this->hasMany(Supplier::class); }
    public function purchaseRequests() { return $this->hasMany(PurchaseRequest::class); }
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
    public function supplierInvoices() { return $this->hasMany(SupplierInvoice::class); }
    public function supplierPayments() { return $this->hasMany(SupplierPayment::class); }
    public function fuelTypes() { return $this->hasMany(FuelType::class); }
    public function fuelTanks() { return $this->hasMany(FuelTank::class); }
    public function fuelReceipts() { return $this->hasMany(FuelReceipt::class); }
    public function fuelIssues() { return $this->hasMany(FuelIssue::class); }
    public function fuelAdjustments() { return $this->hasMany(FuelAdjustment::class); }
    public function fuelStockMovements() { return $this->hasMany(FuelStockMovement::class); }
    public function assetCategories() { return $this->hasMany(AssetCategory::class); }
    public function assets() { return $this->hasMany(Asset::class); }
    public function maintenancePlans() { return $this->hasMany(MaintenancePlan::class); }
    public function maintenanceWorkOrders() { return $this->hasMany(MaintenanceWorkOrder::class); }

    public function departments() { return $this->hasMany(Department::class); }
    public function positions() { return $this->hasMany(Position::class); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function shifts() { return $this->hasMany(Shift::class); }
    public function shiftAssignments() { return $this->hasMany(ShiftAssignment::class); }
    public function attendanceLogs() { return $this->hasMany(AttendanceLog::class); }
    public function leaveTypes() { return $this->hasMany(LeaveType::class); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class); }
    public function overtimeRecords() { return $this->hasMany(OvertimeRecord::class); }
    public function employeeAdvances() { return $this->hasMany(EmployeeAdvance::class); }
    public function salaryComponents() { return $this->hasMany(SalaryComponent::class); }
    public function payrollPeriods() { return $this->hasMany(PayrollPeriod::class); }
    public function payrollRuns() { return $this->hasMany(PayrollRun::class); }
    public function payrollPayments() { return $this->hasMany(PayrollPayment::class); }
}
