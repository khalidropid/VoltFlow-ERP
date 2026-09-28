<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
    Schema::create('integration_sources',function(Blueprint $t){$t->id();$t->string('code',50)->unique();$t->string('name');$t->enum('type',['flutter','legacy','api','import','other'])->default('api');$t->boolean('is_active')->default(true);$t->timestamps();});
    Schema::create('integration_batches',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained()->cascadeOnDelete();$t->uuid('batch_uuid')->unique();$t->string('external_batch_id',150)->nullable();$t->dateTime('started_at');$t->dateTime('completed_at')->nullable();$t->enum('status',['pending','processing','completed','partial','failed'])->default('pending');$t->unsignedInteger('received_count')->default(0);$t->unsignedInteger('processed_count')->default(0);$t->unsignedInteger('failed_count')->default(0);$t->text('error_message')->nullable();$t->timestamps();});
    Schema::create('integration_events',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained()->cascadeOnDelete();$t->foreignId('integration_batch_id')->nullable()->constrained()->nullOnDelete();$t->uuid('event_uuid')->unique();$t->string('entity_type',100);$t->string('external_id',150);$t->string('event_type',80);$t->json('payload');$t->enum('status',['received','processed','ignored','failed'])->default('received');$t->text('error_message')->nullable();$t->dateTime('received_at');$t->dateTime('processed_at')->nullable();$t->timestamps();$t->unique(['integration_source_id','entity_type','external_id','event_type'],'integration_event_external_unique');});
    Schema::create('legacy_id_mappings',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained()->cascadeOnDelete();$t->string('legacy_table',100);$t->string('legacy_id',150);$t->string('entity_type',100);$t->unsignedBigInteger('entity_id');$t->timestamps();$t->unique(['integration_source_id','legacy_table','legacy_id'],'legacy_mapping_source_table_id_unique');});
    Schema::create('user_devices',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('device_id',150);$t->string('platform',30)->nullable();$t->string('app_version',50)->nullable();$t->boolean('is_approved')->default(false);$t->dateTime('approved_at')->nullable();$t->dateTime('last_seen_at')->nullable();$t->timestamps();$t->unique(['user_id','device_id']);});
    Schema::create('collector_locations',function(Blueprint $t){$t->id();$t->foreignId('station_id')->constrained()->restrictOnDelete();$t->foreignId('collector_id')->constrained('users')->restrictOnDelete();$t->decimal('latitude',10,7);$t->decimal('longitude',10,7);$t->dateTime('recorded_at');$t->decimal('accuracy_meters',10,2)->nullable();$t->string('device_id',150)->nullable();$t->timestamps();});
 }
 public function down(): void { foreach(['collector_locations','user_devices','legacy_id_mappings','integration_events','integration_batches','integration_sources'] as $table) Schema::dropIfExists($table); }
};
