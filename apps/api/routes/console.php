<?php

use App\Modules\IdentityAccess\Accounts;
use App\Modules\IdentityAccess\RoleCatalog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('identity:seed-demo', function (Accounts $accounts): int {
    $password = config('laras.demo_password');
    if (!app()->environment('local') || !config('laras.demo_accounts')) {
        $this->error('Akun pengembangan belum diaktifkan di lingkungan lokal.');

        return 1;
    }
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        $this->error('Tetapkan LARAS_DEMO_PASSWORD antara 12 dan 72 byte.');

        return 1;
    }

    $accounts->seedDemo('Administrator Lokal', 'admin@laras.test', $password, RoleCatalog::SUPER_ADMIN);
    $accounts->seedDemo('Pengemudi Lokal', 'driver@laras.test', $password, 'driver');
    $this->info('Akun pengembangan tersedia.');

    return 0;
})->purpose('Provision development-only accounts with an environment-supplied password');

Artisan::command('identity:bootstrap', function (Accounts $accounts): int {
    $validator = Validator::make([
        'name' => trim((string) $this->ask('Nama')),
        'email' => Str::lower(trim((string) $this->ask('Email'))),
        'password' => $this->secret('Kata sandi'),
    ], [
        'name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        'password' => ['required', 'string', 'min:12'],
    ]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $input = $validator->validated();
    if (strlen($input['password']) > 72 || str_contains($input['password'], "\0")) {
        $this->error('Kata sandi harus valid dan maksimal 72 byte.');

        return 1;
    }

    try {
        $accounts->bootstrap($input['name'], $input['email'], $input['password']);
    } catch (DomainException $error) {
        $this->error($error->getMessage());

        return 1;
    }

    $this->info('Administrator utama tersedia.');

    return 0;
})->purpose('Provision the initial administrator interactively');
