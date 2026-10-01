<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('integration_sources',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->string('type');$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('integration_batches',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained('integration_sources');$t->uuid('batch_uuid')->unique();$t->string('external_batch_id')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('completed_at')->nullable();$t->string('status')->default('received');$t->unsignedInteger('received_count')->default(0);$t->unsignedInteger('processed_count')->default(0);$t->unsignedInteger('failed_count')->default(0);$t->text('error_message')->nullable();$t->timestamps();$t->index(['integration_source_id','status']);});
  Schema::create('integration_events',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained('integration_sources');$t->foreignId('integration_batch_id')->nullable()->constrained('integration_batches')->nullOnDelete();$t->uuid('event_uuid')->unique();$t->string('entity_type');$t->string('external_id');$t->string('event_type');$t->jsonb('payload');$t->string('status')->default('received');$t->text('error_message')->nullable();$t->timestamp('received_at')->nullable();$t->timestamp('processed_at')->nullable();$t->timestamps();$t->unique(['integration_source_id','entity_type','external_id','event_type']);$t->index(['status','received_at']);});
  Schema::create('legacy_id_mappings',function(Blueprint $t){$t->id();$t->foreignId('integration_source_id')->constrained('integration_sources');$t->string('legacy_table');$t->string('legacy_id');$t->string('entity_type');$t->unsignedBigInteger('entity_id');$t->timestamps();$t->unique(['integration_source_id','legacy_table','legacy_id']);$t->index(['entity_type','entity_id']);});
  Schema::create('user_devices',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('device_id');$t->string('platform')->nullable();$t->string('app_version')->nullable();$t->boolean('is_approved')->default(false);$t->timestamp('approved_at')->nullable();$t->timestamp('last_seen_at')->nullable();$t->timestamps();$t->unique(['user_id','device_id']);});
  Schema::create('collector_locations',function(Blueprint $t){$t->id();$t->foreignId('station_id')->constrained()->cascadeOnDelete();$t->foreignId('collector_id')->constrained('users');$t->decimal('latitude',10,7);$t->decimal('longitude',10,7);$t->timestamp('recorded_at');$t->decimal('accuracy_meters',10,2)->nullable();$t->string('device_id');$t->timestamps();$t->index(['station_id','collector_id','recorded_at']);});
 }
 public function down(): void {Schema::dropIfExists('collector_locations');Schema::dropIfExists('user_devices');Schema::dropIfExists('legacy_id_mappings');Schema::dropIfExists('integration_events');Schema::dropIfExists('integration_batches');Schema::dropIfExists('integration_sources');}
};