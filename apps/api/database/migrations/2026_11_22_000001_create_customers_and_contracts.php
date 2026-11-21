<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->foreignUuid('category_id')->constrained('customer_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('status', 16)->default('active');
            $table->index(['category_id', 'status']);
            $table->timestampsTz();
        });

        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->index('customer_id');
            $table->timestampsTz();
        });

        Schema::create('pickup_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('address', 500);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unique(['id', 'customer_id']);
            $table->index('customer_id');
            $table->timestampsTz();
        });

        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('billing_name', 160);
            $table->string('address', 500);
            $table->string('email', 254)->nullable();
            $table->unsignedSmallInteger('payment_term_days');
            $table->timestampsTz();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name', 160);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unique(['id', 'customer_id']);
            $table->index(['customer_id', 'status']);
            $table->timestampsTz();
        });

        Schema::create('contract_status_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason', 500)->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['contract_id', 'occurred_at']);
        });

        Schema::create('contract_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('contract_id');
            $table->uuid('customer_id');
            $table->uuid('location_id');
            $table->string('name', 160);
            $table->foreign(['contract_id', 'customer_id'])->references(['id', 'customer_id'])->on('contracts')->restrictOnDelete();
            $table->foreign(['location_id', 'customer_id'])->references(['id', 'customer_id'])->on('pickup_locations')->restrictOnDelete();
            $table->index('contract_id');
            $table->timestampsTz();
        });

        Schema::create('service_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('contract_services')->restrictOnDelete();
            $table->string('pricing_model', 16);
            $table->decimal('unit_rate', 20, 6);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestampsTz();
        });

        Schema::create('pickup_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('series_id');
            $table->foreignUuid('service_id')->constrained('contract_services')->restrictOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->time('slot');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS btree_gist;
            ALTER TABLE customers ADD CONSTRAINT customers_valid_status CHECK (status IN ('active', 'inactive', 'archived'));
            ALTER TABLE customer_contacts ADD CONSTRAINT contacts_have_channel CHECK (phone IS NOT NULL OR email IS NOT NULL);
            ALTER TABLE pickup_locations ADD CONSTRAINT locations_valid_coordinates CHECK (
                (latitude IS NULL AND longitude IS NULL) OR
                (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
            );
            ALTER TABLE billing_profiles ADD CONSTRAINT profiles_valid_term CHECK (payment_term_days BETWEEN 0 AND 365);
            ALTER TABLE contracts ADD CONSTRAINT contracts_valid_dates CHECK (ends_on IS NULL OR starts_on <= ends_on);
            ALTER TABLE contracts ADD CONSTRAINT contracts_valid_status
                CHECK (status IN ('draft', 'active', 'suspended', 'expired', 'terminated'));
            ALTER TABLE service_rates ADD CONSTRAINT service_rates_valid
                CHECK (unit_rate >= 0 AND pricing_model IN ('monthly', 'per_pickup', 'per_kg', 'one_time') AND
                       (effective_until IS NULL OR effective_from < effective_until));
            ALTER TABLE service_rates ADD CONSTRAINT service_rates_no_overlap
                EXCLUDE USING gist (service_id WITH =, daterange(effective_from, effective_until, '[)') WITH &&);
            ALTER TABLE pickup_schedules ADD CONSTRAINT pickup_schedules_valid
                CHECK (weekday BETWEEN 1 AND 7 AND (effective_until IS NULL OR effective_from < effective_until));
            ALTER TABLE pickup_schedules ADD CONSTRAINT pickup_schedules_no_overlap
                EXCLUDE USING gist (service_id WITH =, weekday WITH =, slot WITH =,
                                    daterange(effective_from, effective_until, '[)') WITH &&);
            CREATE INDEX service_rates_service_idx ON service_rates (service_id, effective_from);
            CREATE INDEX pickup_schedules_service_idx ON pickup_schedules (service_id, effective_from);
            CREATE OR REPLACE FUNCTION reject_contract_history_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Contract history is append-only';
            END;
            $$;
            CREATE TRIGGER contract_history_append_only
            BEFORE UPDATE OR DELETE OR TRUNCATE ON contract_status_events
            FOR EACH STATEMENT EXECUTE FUNCTION reject_contract_history_mutation();
            SQL);
    }

    public function down(): void
    {
        foreach (['pickup_schedules', 'service_rates', 'contract_services', 'contract_status_events', 'contracts',
            'billing_profiles', 'pickup_locations', 'customer_contacts', 'customers', 'customer_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS reject_contract_history_mutation()');
    }
};
