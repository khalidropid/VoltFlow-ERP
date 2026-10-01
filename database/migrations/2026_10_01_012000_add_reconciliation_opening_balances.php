<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) { $table->decimal('opening_balance',20,4)->default(0)->after('account_id'); });
        Schema::table('fuel_tanks', function (Blueprint $table) { $table->decimal('opening_quantity',20,4)->default(0)->after('current_quantity'); });
        Schema::table('warehouse_stocks', function (Blueprint $table) { $table->decimal('opening_quantity',20,4)->default(0)->after('quantity'); });
    }

    public function down(): void
    {
        Schema::table('warehouse_stocks', fn(Blueprint $t)=>$t->dropColumn('opening_quantity'));
        Schema::table('fuel_tanks', fn(Blueprint $t)=>$t->dropColumn('opening_quantity'));
        Schema::table('cash_accounts', fn(Blueprint $t)=>$t->dropColumn('opening_balance'));
    }
};