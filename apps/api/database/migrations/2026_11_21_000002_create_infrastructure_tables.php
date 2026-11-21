<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 80);
            $table->uuid('resource_id')->nullable();
            $table->uuid('request_id');
            $table->jsonb('details');
            $table->timestampTz('occurred_at');
            $table->index(['occurred_at', 'id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_audit_log_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Audit logs are append-only';
            END;
            $$;

            CREATE TRIGGER audit_logs_append_only
            BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_logs
            FOR EACH STATEMENT EXECUTE FUNCTION reject_audit_log_mutation();
            SQL);

        Schema::create('reference_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('value', 120);
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestampTz('failed_at')->useCurrent();
        });
        DB::statement('ALTER TABLE failed_jobs ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('reference_values');
        Schema::dropIfExists('audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS reject_audit_log_mutation()');
    }
};
