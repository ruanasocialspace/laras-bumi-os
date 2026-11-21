<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_normalized CHECK (email = lower(email))');

        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('code', 80)->unique();
                $table->string('name', 120);
            });
        }

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->unique(['user_id', 'role_id']);
            $table->index(['role_id', 'user_id']);
        });
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('permission_id')->constrained()->restrictOnDelete();
            $table->unique(['role_id', 'permission_id']);
            $table->index(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        foreach (['role_permissions', 'user_roles', 'permissions', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
