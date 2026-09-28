<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
    Schema::table('invoices',function(Blueprint $t){$t->foreignId('billing_cycle_id')->nullable()->after('tariff_id')->constrained()->nullOnDelete();$t->index(['station_id','billing_cycle_id','status']);});
    Schema::table('generators',function(Blueprint $t){$t->foreignId('asset_id')->nullable()->after('station_id')->constrained('assets')->nullOnDelete();$t->index(['station_id','asset_id']);});
    Schema::table('assets',function(Blueprint $t){
        $t->foreignId('asset_account_id')->nullable()->after('purchase_cost')->constrained('chart_of_accounts')->nullOnDelete();
        $t->foreignId('accumulated_depreciation_account_id')->nullable()->after('asset_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        $t->foreignId('depreciation_expense_account_id')->nullable()->after('accumulated_depreciation_account_id')->constrained('chart_of_accounts')->nullOnDelete();
    });
    Schema::table('items',function(Blueprint $t){
        $t->foreignId('inventory_account_id')->nullable()->after('standard_cost')->constrained('chart_of_accounts')->nullOnDelete();
        $t->foreignId('cogs_account_id')->nullable()->after('inventory_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        $t->foreignId('expense_account_id')->nullable()->after('cogs_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        $t->index(['inventory_account_id','cogs_account_id','expense_account_id']);
    });
    Schema::table('fuel_types',function(Blueprint $t){
        $t->foreignId('inventory_account_id')->nullable()->after('unit')->constrained('chart_of_accounts')->nullOnDelete();
        $t->foreignId('consumption_expense_account_id')->nullable()->after('inventory_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        $t->index(['inventory_account_id','consumption_expense_account_id']);
    });
    Schema::table('warehouses',function(Blueprint $t){$t->foreignId('inventory_account_id')->nullable()->after('type')->constrained('chart_of_accounts')->nullOnDelete();});
    Schema::table('maintenance_work_orders',function(Blueprint $t){$t->foreignId('cost_center_id')->nullable()->after('asset_id')->constrained()->nullOnDelete();});
    Schema::table('purchase_orders',function(Blueprint $t){$t->foreignId('cost_center_id')->nullable()->after('purchase_request_id')->constrained()->nullOnDelete();});
    Schema::table('departments',function(Blueprint $t){$t->foreignId('cost_center_id')->nullable()->after('station_id')->constrained()->nullOnDelete();});
 }
 public function down(): void {
    foreach([
      ['departments',['cost_center_id'],'cost_center_id'],['purchase_orders',['cost_center_id'],'cost_center_id'],['maintenance_work_orders',['cost_center_id'],'cost_center_id'],
      ['warehouses',['inventory_account_id'],'inventory_account_id'],['fuel_types',['consumption_expense_account_id','inventory_account_id'],null],
      ['items',['expense_account_id','cogs_account_id','inventory_account_id'],null],['assets',['depreciation_expense_account_id','accumulated_depreciation_account_id','asset_account_id'],null],
      ['generators',['asset_id'],'asset_id'],['invoices',['billing_cycle_id'],'billing_cycle_id']
    ] as $x){
      Schema::table($x[0],function(Blueprint $t)use($x){foreach($x[1] as $c)$t->dropForeign([$c]);$t->dropColumn($x[1]);});
    }
 }
};
