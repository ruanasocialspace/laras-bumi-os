<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

final class RoleCatalog
{
    public const SUPER_ADMIN = 'super_admin';

    public const PERMISSIONS = [
        'identity.manage' => 'Kelola pengguna dan peran',
        'settings.manage' => 'Kelola pengaturan',
        'audit.read' => 'Baca log audit',
        'customers.read' => 'Baca pelanggan',
        'customers.write' => 'Kelola pelanggan',
        'customers.categories.manage' => 'Kelola kategori pelanggan',
        'contracts.read' => 'Baca kontrak',
        'contracts.write' => 'Kelola draf kontrak',
        'contracts.activate' => 'Aktifkan kontrak',
        'contracts.pricing.write' => 'Kelola harga kontrak',
        'collections.read' => 'Baca pengambilan',
        'collections.dispatch' => 'Kelola penugasan pengambilan',
        'collections.drive' => 'Kerjakan penugasan pengambilan',
        'waste.read' => 'Baca data sampah',
        'waste.record' => 'Catat penerimaan dan penimbangan',
        'waste.post' => 'Posting transaksi material',
        'waste.approve' => 'Setujui transaksi material',
        'waste.configure' => 'Kelola konfigurasi material',
        'production.read' => 'Baca data produksi',
        'production.operate' => 'Kelola pekerjaan produksi',
        'production.qc.approve' => 'Setujui hasil kendali mutu',
        'production.eligibility.approve' => 'Setujui kelayakan bahan',
        'production.configure' => 'Kelola konfigurasi produksi',
        'finance.read' => 'Baca data keuangan',
        'finance.bill' => 'Kelola tagihan',
        'finance.payments.record' => 'Catat pembayaran',
        'finance.payments.verify' => 'Verifikasi pembayaran',
        'finance.adjustments.request' => 'Ajukan koreksi keuangan',
        'finance.adjustments.approve' => 'Setujui koreksi keuangan',
        'finance.expenses.record' => 'Catat pengeluaran',
        'finance.tax.configure' => 'Kelola konfigurasi pajak',
    ];

    public const ROLES = [
        self::SUPER_ADMIN => ['Administrator Utama', ['identity.manage', 'settings.manage', 'audit.read']],
        'owner' => ['Pemilik', ['audit.read', 'customers.read', 'contracts.read', 'collections.read',
            'waste.read', 'waste.approve', 'production.read', 'finance.read', 'finance.adjustments.approve']],
        'operations_manager' => ['Manajer Operasional', ['customers.read', 'customers.write',
            'customers.categories.manage', 'contracts.read', 'contracts.write', 'contracts.activate',
            'contracts.pricing.write', 'collections.read', 'collections.dispatch', 'waste.read',
            'waste.approve', 'waste.configure', 'production.read', 'production.configure']],
        'operations_admin' => ['Admin Operasional', ['customers.read', 'customers.write',
            'contracts.read', 'contracts.write', 'collections.read', 'collections.dispatch']],
        'driver' => ['Pengemudi / Kru Pengambilan', ['collections.drive']],
        'weighing_officer' => ['Petugas Timbang', ['waste.read', 'waste.record', 'waste.post']],
        'production_operator' => ['Operator Produksi', ['production.read', 'production.operate']],
        'finance_officer' => ['Petugas Keuangan', ['customers.read', 'finance.read', 'finance.bill', 'finance.payments.record',
            'finance.adjustments.request', 'finance.expenses.record']],
    ];
}
