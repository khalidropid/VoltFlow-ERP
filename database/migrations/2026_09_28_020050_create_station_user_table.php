<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('station_user', function (Blueprint $table) {
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->primary(['station_id', 'user_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('station_user'); }
};
