<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\IdentityAccess\Permission;
use App\Modules\IdentityAccess\Role;
use App\Modules\IdentityAccess\RoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = [];
            foreach (RoleCatalog::PERMISSIONS as $code => $name) {
                $permissions[$code] = Permission::updateOrCreate(['code' => $code], ['name' => $name])->id;
            }

            foreach (RoleCatalog::ROLES as $code => [$name, $codes]) {
                $role = Role::updateOrCreate(['code' => $code], ['name' => $name]);
                $wanted = array_map(fn (string $permission): string => $permissions[$permission], $codes);
                DB::table('role_permissions')->where('role_id', $role->id)->whereNotIn('permission_id', $wanted)->delete();

                foreach ($wanted as $permissionId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'id' => Str::uuid()->toString(),
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            foreach (['currency' => 'IDR', 'mass_unit' => 'kg', 'timezone' => 'Asia/Jakarta'] as $code => $value) {
                DB::table('reference_values')->upsert(
                    ['id' => Str::uuid()->toString(), 'code' => $code, 'value' => $value],
                    ['code'],
                    ['value'],
                );
            }

            foreach (['RUMAH_TANGGA' => 'Rumah Tangga', 'USAHA' => 'Usaha', 'INSTANSI' => 'Instansi', 'LAINNYA' => 'Lainnya'] as $code => $name) {
                DB::table('customer_categories')->insertOrIgnore(['id' => Str::uuid()->toString(), 'code' => $code, 'name' => $name]);
            }
        });
    }
}
