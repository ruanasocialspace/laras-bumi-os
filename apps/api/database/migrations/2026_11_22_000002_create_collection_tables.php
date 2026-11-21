<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('registration', 24)->unique();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('pickup_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->uuid('location_id');
            $table->foreign(['location_id', 'customer_id'])->references(['id', 'customer_id'])->on('pickup_locations')->restrictOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained('contract_services')->restrictOnDelete();
            $table->foreignUuid('schedule_id')->nullable()->constrained('pickup_schedules')->restrictOnDelete();
            $table->uuid('series_id')->nullable();
            $table->foreignUuid('driver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('service_date');
            $table->time('slot');
            $table->string('status', 16)->default('scheduled');
            $table->unsignedInteger('revision')->default(0);
            $table->string('location_name', 120);
            $table->string('address', 500);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestampsTz();
            $table->unique(['series_id', 'service_date', 'slot']);
            $table->index(['service_date', 'status']);
            $table->index(['driver_id', 'service_date']);
            $table->index(['customer_id', 'service_date']);
        });

        Schema::create('pickup_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('pickup_tasks')->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 16);
            $table->string('request_hash', 64)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('captured_at');
            $table->timestampTz('received_at');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->decimal('accuracy_m', 9, 2)->nullable();
            $table->unique(['task_id', 'sequence']);
        });

        Schema::create('pickup_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('pickup_tasks')->restrictOnDelete();
            $table->foreignUuid('uploader_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('pickup_events')->restrictOnDelete();
            $table->string('original_hash', 64);
            $table->string('object_key', 100)->unique();
            $table->unsignedInteger('bytes');
            $table->timestampTz('created_at');
            $table->index(['task_id', 'event_id']);
        });

        Schema::create('pickup_waste_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->unique()->constrained('pickup_events')->restrictOnDelete();
            $table->decimal('weight_kg', 12, 3);
            $table->string('weight_basis', 20);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE pickup_tasks ADD CONSTRAINT pickup_tasks_status_check
                CHECK (status IN ('scheduled', 'assigned', 'en_route', 'arrived', 'completed', 'failed', 'canceled'));
            ALTER TABLE pickup_tasks ADD CONSTRAINT pickup_tasks_assignment_check
                CHECK (status IN ('scheduled', 'canceled') OR (driver_id IS NOT NULL AND vehicle_id IS NOT NULL));
            ALTER TABLE pickup_waste_entries ADD CONSTRAINT pickup_weight_check
                CHECK (weight_kg > 0 AND weight_basis IN ('estimated', 'field_measured'));
            CREATE OR REPLACE FUNCTION reject_pickup_event_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Pickup events are append-only';
            END;
            $$;
            CREATE TRIGGER pickup_events_append_only
            BEFORE UPDATE OR DELETE OR TRUNCATE ON pickup_events
            FOR EACH STATEMENT EXECUTE FUNCTION reject_pickup_event_mutation();
            SQL);
    }

    public function down(): void
    {
        foreach (['pickup_waste_entries', 'pickup_evidence', 'pickup_events', 'pickup_tasks', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS reject_pickup_event_mutation()');
    }
};
