<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $teams = config('permission.teams', false);
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (!$tableNames) {
            throw new RuntimeException('Spatie permission table names are not configured.');
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key']);
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teams) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames, $teams, $tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type']);
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key']);
                $table->primary([$columnNames['team_foreign_key'], 'permission_id', $columnNames['model_morph_key'], 'model_type']);
            } else {
                $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type']);
            }
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames, $teams, $tableNames) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type']);
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key']);
                $table->primary([$columnNames['team_foreign_key'], 'role_id', $columnNames['model_morph_key'], 'model_type']);
            } else {
                $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type']);
            }
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $t = config('permission.table_names');

        if (!$t) {
            return;
        }

        Schema::dropIfExists($t['role_has_permissions']);
        Schema::dropIfExists($t['model_has_roles']);
        Schema::dropIfExists($t['model_has_permissions']);
        Schema::dropIfExists($t['roles']);
        Schema::dropIfExists($t['permissions']);
    }
};
