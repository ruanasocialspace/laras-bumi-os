<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use App\Modules\Customers\CustomerData;
use App\Modules\IdentityAccess\User;
use App\Modules\SharedInfrastructure\AuditLog;
use App\Modules\SharedInfrastructure\BusinessDate;
use App\Modules\SharedInfrastructure\RevisionConflict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PickupWorkflow
{
    public function __construct(
        private readonly CollectionData $data,
        private readonly CustomerData $customers,
        private readonly AuditLog $audit,
    ) {
    }

    public function manual(User $actor, array $input): object
    {
        return DB::transaction(function () use ($actor, $input): object {
            $id = strtolower($input['client_task_id']);
            $customer = $this->customers->find($input['customer_id'], lock: true);
            $existing = DB::table('pickup_tasks')->where('id', $id)->first();
            if ($existing !== null) {
                $this->assertManualRetry($existing, $actor, $input);

                return $this->data->visibleTask($actor, $id);
            }

            abort_unless($customer->status === 'active', 409);
            if ($input['service_date'] < BusinessDate::today()) {
                throw ValidationException::withMessages(['service_date' => 'Pilih tanggal hari ini atau mendatang.']);
            }
            $location = $this->customers->location($customer->id, $input['location_id']);
            $created = $this->createTask($id, $location, $input['service_date'], $input['slot'], $actor->id);
            if (!$created) {
                $this->assertManualRetry($this->data->task($id), $actor, $input);
            }

            return $this->data->visibleTask($actor, $id);
        }, 3);
    }

    public function createTask(string $id, object $location, string $date, string $slot, ?string $actor, ?object $schedule = null): bool
    {
        $inserted = DB::table('pickup_tasks')->insertOrIgnore([
            'id' => $id, 'customer_id' => $location->customer_id, 'location_id' => $location->id,
            'service_id' => $schedule?->service_id, 'schedule_id' => $schedule?->id, 'series_id' => $schedule?->series_id,
            'created_by' => $actor, 'service_date' => $date, 'slot' => $slot, 'location_name' => $location->name,
            'address' => $location->address, 'latitude' => $location->latitude, 'longitude' => $location->longitude,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return false;
        }

        $this->addHistory($id, 0, 'scheduled', $actor, now()->toISOString());
        $this->audit->record($actor, 'collection.task_created', 'pickup_task', $id, ['source' => $schedule === null ? 'manual' : 'recurring']);

        return true;
    }

    public function assign(User $actor, string $id, array $input): object
    {
        return DB::transaction(function () use ($actor, $id, $input): object {
            $task = $this->data->task($id, lock: true);
            $driver = User::query()->find($input['driver_id']);
            $vehicle = DB::table('vehicles')->where('id', $input['vehicle_id'])->lockForUpdate()->first();
            if ($driver === null || !$driver->hasPermission('collections.drive')) {
                throw ValidationException::withMessages(['driver_id' => 'Pilih pengemudi aktif dengan izin pengambilan.']);
            }
            if ($vehicle === null || !$vehicle->active) {
                throw ValidationException::withMessages(['vehicle_id' => 'Pilih kendaraan aktif.']);
            }
            abort_unless(in_array($task->status, ['scheduled', 'assigned'], true), 409);
            if ($task->status === 'assigned' && $task->driver_id === $driver->id && $task->vehicle_id === $vehicle->id) {
                return $this->data->visibleTask($actor, $id);
            }

            $revision = $this->nextRevision($task, $input['expected_revision']);
            $this->change($task->id, $revision, 'assigned', ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);
            $this->addHistory($task->id, $revision, 'assigned', $actor->id, now()->toISOString());
            $this->audit->record($actor->id, 'collection.assigned', 'pickup_task', $task->id, [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

            return $this->data->visibleTask($actor, $id);
        }, 3);
    }

    public function cancel(User $actor, string $id, array $input): object
    {
        return DB::transaction(function () use ($actor, $id, $input): object {
            $task = $this->data->task($id, lock: true);
            abort_unless(in_array($task->status, CollectionData::OPEN, true), 409);
            $revision = $this->nextRevision($task, $input['expected_revision']);
            $this->change($task->id, $revision, 'canceled');
            $this->addHistory($task->id, $revision, 'canceled', $actor->id, now()->toISOString(), ['reason' => $input['reason']]);
            $this->audit->record($actor->id, 'collection.canceled', 'pickup_task', $task->id);

            return $this->data->visibleTask($actor, $id);
        }, 3);
    }

    public function driverEvent(User $actor, string $taskId, array $input): array
    {
        $eventId = strtolower($input['client_event_id']);
        $input['expected_revision'] = (int) $input['expected_revision'];
        if (isset($input['evidence_ids'])) {
            $input['evidence_ids'] = array_map(strtolower(...), $input['evidence_ids']);
            sort($input['evidence_ids']);
        }
        ksort($input);
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $captured = CarbonImmutable::parse($input['captured_at']);

        return DB::transaction(function () use ($actor, $taskId, $eventId, $input, $hash, $captured): array {
            $task = $this->data->task($taskId, lock: true);
            abort_unless($task->driver_id === $actor->id, 404);
            $existing = DB::table('pickup_events')->where('id', $eventId)->first();
            if ($existing !== null) {
                abort_unless($existing->task_id === $task->id && $existing->actor_id === $actor->id && $existing->request_hash === $hash, 409);

                return ['event_id' => $eventId, 'sequence' => $existing->sequence, 'status' => $existing->type, 'revision' => $existing->sequence];
            }

            if ($captured->greaterThan(now()->addMinutes(15))) {
                throw ValidationException::withMessages(['captured_at' => 'Periksa waktu perangkat sebelum menyinkronkan.']);
            }

            $type = $input['type'];
            $allowed = match ($task->status) {
                'assigned' => ['en_route', 'failed'],
                'en_route' => ['arrived', 'failed'],
                'arrived' => ['completed', 'failed'],
                default => [],
            };
            abort_unless(in_array($type, $allowed, true), 409);
            $revision = $this->nextRevision($task, $input['expected_revision']);

            $details = ['request_hash' => $hash, 'reason' => $input['reason'] ?? null];
            if ($type === 'completed') {
                $details += ['latitude' => $input['latitude'], 'longitude' => $input['longitude'], 'accuracy_m' => $input['accuracy_m']];
            }
            $this->addHistory($task->id, $revision, $type, $actor->id, $captured->toISOString(), $details, $eventId);

            if ($type === 'completed') {
                $this->attachEvidence($task->id, $actor->id, $eventId, $input['evidence_ids']);
                DB::table('pickup_waste_entries')->insert([
                    'id' => Str::uuid()->toString(), 'event_id' => $eventId,
                    'weight_kg' => $input['weight_kg'], 'weight_basis' => $input['weight_basis'],
                ]);
            }
            $this->change($task->id, $revision, $type);
            $this->audit->record($actor->id, 'collection.driver_event', 'pickup_task', $task->id, ['type' => $type, 'revision' => $revision]);

            return ['event_id' => $eventId, 'sequence' => $revision, 'status' => $type, 'revision' => $revision];
        }, 3);
    }

    private function attachEvidence(string $task, string $actor, string $event, array $ids): void
    {
        $count = DB::table('pickup_evidence')->where('task_id', $task)->where('uploader_id', $actor)
            ->whereNull('event_id')->whereIn('id', $ids)->update(['event_id' => $event]);
        if ($count !== count($ids)) {
            throw ValidationException::withMessages(['evidence_ids' => 'Bukti foto belum tersedia untuk pengambilan ini.']);
        }
    }

    private function assertManualRetry(object $task, User $actor, array $input): void
    {
        abort_unless($task->created_by === $actor->id && $task->customer_id === strtolower($input['customer_id'])
            && $task->location_id === strtolower($input['location_id']) && $task->service_date === $input['service_date']
            && substr($task->slot, 0, 5) === $input['slot'], 409);
    }

    private function nextRevision(object $task, int|string $expected): int
    {
        if ($task->revision !== (int) $expected) {
            throw new RevisionConflict($task->revision);
        }

        return $task->revision + 1;
    }

    private function change(string $id, int $revision, string $status, array $fields = []): void
    {
        DB::table('pickup_tasks')->where('id', $id)->update($fields + ['status' => $status, 'revision' => $revision, 'updated_at' => now()]);
    }

    private function addHistory(
        string $task,
        int $sequence,
        string $type,
        ?string $actor,
        string $captured,
        array $details = [],
        ?string $id = null,
    ): void {
        $inserted = DB::table('pickup_events')->insertOrIgnore($details + [
            'id' => $id ?? Str::uuid()->toString(), 'task_id' => $task, 'actor_id' => $actor, 'sequence' => $sequence,
            'type' => $type, 'captured_at' => $captured, 'received_at' => now(),
        ]);
        abort_unless($inserted === 1, 409);
    }
}
