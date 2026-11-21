<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'max' => [
        'string' => ':attribute maksimal :max karakter.',
        'numeric' => ':attribute maksimal :max.',
        'array' => ':attribute maksimal :max pilihan.',
    ],
    'min' => [
        'string' => ':attribute minimal :min karakter.',
        'array' => 'Pilih minimal :min untuk :attribute.',
    ],
    'array' => ':attribute harus berupa daftar.',
    'list' => ':attribute harus berupa daftar berurutan.',
    'boolean' => ':attribute harus berupa nilai benar atau salah.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'distinct' => 'Pilihan :attribute tidak boleh berulang.',
    'exists' => 'Pilihan :attribute tidak tersedia.',
    'unique' => ':attribute sudah digunakan.',
    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'kata sandi',
        'role_codes' => 'peran',
        'role_codes.*' => 'peran',
        'active' => 'status akun',
        'per_page' => 'jumlah per halaman',
        'page' => 'halaman',
    ],
];
