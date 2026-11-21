<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Collections\CollectionData;
use App\Modules\IdentityAccess\User;
use App\Modules\SharedInfrastructure\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Customers
{
    public function __construct(
        private readonly CustomerData $data,
        private readonly AuditLog $audit,
        private readonly CollectionData $collections,
    ) {
    }

    public function create(User $actor, string $type, array $fields): object
    {
        $table = match ($type) {
            'customer' => 'customers',
            'customer_category' => 'customer_categories',
        };

        try {
            return DB::transaction(function () use ($actor, $type, $table, $fields): object {
                $id = Str::uuid()->toString();
                $timestamps = $type === 'customer' ? ['created_at' => now(), 'updated_at' => now()] : [];
                DB::table($table)->insert(['id' => $id] + $fields + $timestamps);
                $this->audit->record($actor->id, $type.'.created', $type, $id);

                return DB::table($table)->where('id', $id)->first();
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() === '23505') {
                throw ValidationException::withMessages(['code' => 'Kode sudah digunakan.']);
            }

            throw $error;
        }
    }

    public function update(User $actor, string $id, array $fields): object
    {
        return DB::transaction(function () use ($actor, $id, $fields): object {
            $customer = $this->data->find($id, lock: true);
            abort_if($customer->status === 'archived', 409);
            $target = $fields['status'] ?? $customer->status;

            if ($target !== $customer->status && $target !== 'active') {
                abort_if($this->data->hasOpenContracts($id, includeDraft: $target === 'archived'), 409);
            }
            if ($target === 'archived') {
                abort_if($this->collections->hasOpenForCustomer($id), 409);
            }

            DB::table('customers')->where('id', $id)->update($fields + ['updated_at' => now()]);
            $this->audit->record($actor->id, 'customer.updated', 'customer', $id, [
                'fields' => array_keys($fields), 'from_status' => $customer->status, 'to_status' => $target,
            ]);

            return $this->data->find($id);
        }, 3);
    }

    public function saveRelated(User $actor, string $customerId, string $type, array $fields, ?string $recordId = null): object
    {
        $table = match ($type) {
            'customer_contact' => 'customer_contacts',
            'pickup_location' => 'pickup_locations',
            'billing_profile' => 'billing_profiles',
        };

        return DB::transaction(function () use ($actor, $customerId, $type, $table, $fields, $recordId): object {
            $customer = $this->data->find($customerId, lock: true);
            abort_if($customer->status === 'archived', 409);

            $query = DB::table($table)->where('customer_id', $customerId);
            $existing = match (true) {
                $recordId !== null => $query->where('id', $recordId)->first(),
                $type === 'billing_profile' => $query->first(),
                default => null,
            };
            abort_if($recordId !== null && $existing === null, 404);
            $merged = $fields + (array) $existing;
            if ($type === 'customer_contact' && ($merged['phone'] ?? null) === null && ($merged['email'] ?? null) === null) {
                throw ValidationException::withMessages(['phone' => 'Isi minimal satu nomor telepon atau alamat email.']);
            }
            if ($type === 'pickup_location' && (($merged['latitude'] ?? null) === null) !== (($merged['longitude'] ?? null) === null)) {
                throw ValidationException::withMessages(['latitude' => 'Isi lintang dan bujur bersama-sama.']);
            }

            $id = $existing->id ?? Str::uuid()->toString();
            $values = ['updated_at' => now()] + $fields;
            if ($existing === null) {
                DB::table($table)->insert($values + ['id' => $id, 'customer_id' => $customerId, 'created_at' => now()]);
            } else {
                DB::table($table)->where('id', $id)->update($values);
            }

            $this->audit->record($actor->id, $type.'.saved', $type, $id, ['customer_id' => $customerId, 'fields' => array_keys($fields)]);

            return DB::table($table)->where('id', $id)->first();
        }, 3);
    }
}
