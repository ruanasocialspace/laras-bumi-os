<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use App\Modules\Customers\CustomerData;
use App\Modules\IdentityAccess\User;
use App\Modules\SharedInfrastructure\AuditLog;
use App\Modules\SharedInfrastructure\BusinessDate;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Contracts
{
    public function __construct(
        private readonly ContractData $data,
        private readonly CustomerData $customers,
        private readonly AuditLog $audit,
    ) {
    }

    public function create(User $actor, array $fields): object
    {
        return $this->write(function () use ($actor, $fields): object {
            $customer = $this->customers->find($fields['customer_id'], lock: true);
            abort_unless($customer->status === 'active', 409);
            $this->validDates($fields['starts_on'], $fields['ends_on'] ?? null);

            $contract = $this->insert('contracts', $fields);
            $this->recordStatus($actor, $contract->id, null, 'draft', null);

            return $this->data->withEffectiveStatus($contract);
        });
    }

    public function update(User $actor, string $id, array $fields): object
    {
        return $this->write(function () use ($actor, $id, $fields): object {
            [$contract] = $this->lockedContract($id);
            abort_unless($contract->status === 'draft', 409);
            $next = (object) array_replace((array) $contract, $fields);
            $this->validDates($next->starts_on, $next->ends_on);

            if ($next->starts_on !== $contract->starts_on || $next->ends_on !== $contract->ends_on) {
                foreach ([$this->data->schedules($id), DB::table('service_rates')->whereIn(
                    'service_id',
                    $this->data->services($id)->select('id'),
                )] as $records) {
                    foreach ($records->get() as $record) {
                        $this->validWindow($next, (array) $record);
                    }
                }
            }

            $saved = $this->updateRow('contracts', $id, $fields);
            $this->audit->record($actor->id, 'contract.updated', 'contract', $id, ['fields' => array_keys($fields)]);

            return $this->data->withEffectiveStatus($saved);
        });
    }

    public function transition(User $actor, string $id, array $input): object
    {
        return $this->write(function () use ($actor, $id, $input): object {
            [$contract, $customer] = $this->lockedContract($id);
            $target = $input['status'];

            $allowed = match ($contract->status) {
                'draft' => ['active', 'terminated'],
                'active' => ['suspended', 'expired', 'terminated'],
                'suspended' => ['active', 'expired', 'terminated'],
                default => [],
            };
            abort_unless(in_array($target, $allowed, true), 409);
            abort_if($this->data->expired($contract) !== ($target === 'expired'), 409);

            if ($target === 'active') {
                abort_unless($customer->status === 'active', 409);
                $this->validateActivation($contract);
            }

            $saved = $this->updateRow('contracts', $id, ['status' => $target]);
            $this->recordStatus($actor, $id, $contract->status, $target, $input['reason'] ?? null);

            return $this->data->withEffectiveStatus($saved);
        });
    }

    public function saveService(User $actor, string $contractId, array $fields, ?string $serviceId = null): object
    {
        return $this->write(function () use ($actor, $contractId, $fields, $serviceId): object {
            [$contract] = $this->lockedContract($contractId);
            abort_unless($contract->status === 'draft', 409);
            $this->customers->location($contract->customer_id, $fields['location_id']);
            if ($serviceId !== null) {
                $this->data->service($contractId, $serviceId);
            }

            $service = $serviceId === null
                ? $this->insert('contract_services', $fields + ['contract_id' => $contractId, 'customer_id' => $contract->customer_id])
                : $this->updateRow('contract_services', $serviceId, $fields);
            $this->audit->record($actor->id, 'contract.service_saved', 'contract_service', $service->id, ['contract_id' => $contractId]);

            return $service;
        });
    }

    public function saveRate(User $actor, string $contractId, string $serviceId, array $fields, ?string $rateId = null): object
    {
        return $this->write(function () use ($actor, $contractId, $serviceId, $fields, $rateId): object {
            [$contract] = $this->lockedContract($contractId);
            $this->data->service($contractId, $serviceId);
            $fields['effective_until'] ??= $this->endExclusive($contract);
            $this->checkEffectiveMutation($contract, $fields, $rateId);
            $query = DB::table('service_rates')->where('service_id', $serviceId);
            if ($rateId !== null) {
                abort_unless((clone $query)->where('id', $rateId)->exists(), 404);
            }
            $previous = null;
            if (!empty($fields['replace_open'])) {
                $contractEnd = $this->endExclusive($contract);
                $previous = (clone $query)->where('effective_until', $contractEnd)->where('effective_from', '<', $fields['effective_from'])
                    ->when($rateId, fn ($rates) => $rates->where('id', '<>', $rateId))->first();
                if ($previous === null) {
                    throw ValidationException::withMessages(['replace_open' => 'Tidak ada tarif terbuka sebelumnya untuk ditutup.']);
                }

                $this->updateRow('service_rates', $previous->id, ['effective_until' => $fields['effective_from']]);
            }

            $values = Arr::except($fields, 'replace_open');
            $rate = $rateId === null
                ? $this->insert('service_rates', $values + ['service_id' => $serviceId])
                : $this->updateRow('service_rates', $rateId, $values);
            $this->audit->record($actor->id, 'contract.rate_saved', 'service_rate', $rate->id, [
                'contract_id' => $contractId, 'closed_rate_id' => $previous?->id,
            ]);

            return $rate;
        });
    }

    public function saveSchedule(User $actor, string $contractId, array $fields, ?string $scheduleId = null): object
    {
        return $this->write(function () use ($actor, $contractId, $fields, $scheduleId): object {
            [$contract] = $this->lockedContract($contractId);
            $this->data->service($contractId, $fields['service_id']);
            $fields['effective_until'] ??= $this->endExclusive($contract);
            $this->checkEffectiveMutation($contract, $fields, $scheduleId);
            if ($scheduleId !== null) {
                abort_unless($this->data->schedules($contractId)->where('id', $scheduleId)->exists(), 404);
            }

            $schedule = $scheduleId === null
                ? $this->insert('pickup_schedules', $fields + ['series_id' => Str::uuid()->toString()])
                : $this->updateRow('pickup_schedules', $scheduleId, $fields);
            $this->audit->record($actor->id, 'contract.schedule_saved', 'pickup_schedule', $schedule->id, ['contract_id' => $contractId]);

            return $schedule;
        });
    }

    private function lockedContract(string $id): array
    {
        $customer = $this->customers->find($this->data->find($id)->customer_id, lock: true);

        return [$this->data->find($id, lock: true), $customer];
    }

    private function validDates(string $start, ?string $end): void
    {
        if ($end !== null && $end < $start) {
            throw ValidationException::withMessages(['ends_on' => 'Tanggal akhir harus pada atau setelah tanggal mulai.']);
        }
    }

    private function validWindow(object $contract, array $fields): void
    {
        $end = $fields['effective_until'] ?? null;
        $contractEnd = $this->endExclusive($contract);

        if ($fields['effective_from'] < $contract->starts_on ||
            ($contractEnd !== null && ($fields['effective_from'] >= $contractEnd || $end === null || $end > $contractEnd))) {
            throw ValidationException::withMessages(['effective_from' => 'Masa berlaku harus berada dalam rentang kontrak.']);
        }
    }

    private function endExclusive(object $contract): ?string
    {
        return $contract->ends_on === null ? null : CarbonImmutable::parse($contract->ends_on)->addDay()->toDateString();
    }

    private function checkEffectiveMutation(object $contract, array $fields, ?string $id): void
    {
        $this->validWindow($contract, $fields);
        if ($contract->status !== 'draft') {
            abort_unless($id === null && !$this->data->expired($contract)
                && in_array($contract->status, ['active', 'suspended'], true)
                && $fields['effective_from'] > BusinessDate::today(), 409);
        }
    }

    private function validateActivation(object $contract): void
    {
        $anchor = max(BusinessDate::today(), $contract->starts_on);
        abort_if($contract->ends_on !== null && $contract->ends_on < $anchor, 409);
        $services = $this->data->services($contract->id)->get();
        if ($services->isEmpty()) {
            throw ValidationException::withMessages(['services' => 'Tambahkan minimal satu layanan sebelum mengaktifkan kontrak.']);
        }

        foreach ($services as $service) {
            $this->customers->location($contract->customer_id, $service->location_id);
            if ($this->data->rateOn($service->id, $anchor) === null) {
                throw ValidationException::withMessages(['rates' => 'Setiap layanan harus memiliki tarif yang berlaku pada tanggal aktivasi.']);
            }

            $hasOccurrence = DB::table('pickup_schedules')->where('service_id', $service->id)->get()
                ->contains(function (object $schedule) use ($anchor): bool {
                    $first = CarbonImmutable::parse(max($anchor, $schedule->effective_from), 'Asia/Jakarta');
                    $offset = ((int) $schedule->weekday - $first->dayOfWeekIso + 7) % 7;

                    $date = $first->addDays($offset)->toDateString();

                    return ($schedule->effective_until === null || $date < $schedule->effective_until)
                        && $this->data->rateOn($schedule->service_id, $date) !== null;
                });
            if (!$hasOccurrence) {
                throw ValidationException::withMessages(['schedules' => 'Setiap layanan harus memiliki jadwal mendatang dengan tarif yang berlaku.']);
            }
        }
    }

    private function recordStatus(User $actor, string $contractId, ?string $from, string $to, ?string $reason): void
    {
        DB::table('contract_status_events')->insert([
            'id' => Str::uuid()->toString(), 'contract_id' => $contractId, 'actor_id' => $actor->id,
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'occurred_at' => now(),
        ]);
        $this->audit->record($actor->id, 'contract.status_changed', 'contract', $contractId, ['from_status' => $from, 'to_status' => $to]);
    }

    private function insert(string $table, array $values): object
    {
        $id = Str::uuid()->toString();
        DB::table($table)->insert($values + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table($table)->where('id', $id)->first();
    }

    private function updateRow(string $table, string $id, array $values): object
    {
        DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);

        return DB::table($table)->where('id', $id)->first();
    }

    private function write(Closure $work): object
    {
        try {
            return DB::transaction($work, 3);
        } catch (QueryException $error) {
            switch ((string) $error->getCode()) {
                case '23505':
                    throw ValidationException::withMessages(['code' => 'Kode sudah digunakan.']);
                case '23P01':
                    throw ValidationException::withMessages(['effective_from' => 'Rentang berlaku bertumpang tindih dengan data yang sudah ada.']);
                default:
                    throw $error;
            }
        }
    }
}
