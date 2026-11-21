<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use App\Modules\Customers\CustomerData;
use App\Modules\SharedInfrastructure\ApiPage;
use App\Modules\SharedInfrastructure\AuditLog;
use App\Modules\SharedInfrastructure\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CollectionController
{
    public function __construct(
        private readonly CollectionData $data,
        private readonly PickupWorkflow $workflow,
        private readonly PickupPhotos $photos,
    ) {
    }

    public function tasks(Request $request): JsonResponse
    {
        $input = $request->validate(ApiPage::rules() + [
            'service_date' => ['sometimes', 'date_format:Y-m-d'],
            'history' => ['sometimes', 'boolean'],
            'assigned' => ['sometimes', 'boolean'],
        ]);
        $actor = $request->user();
        $query = $this->data->visibleTasks($actor);
        if (!empty($input['assigned'])) {
            abort_unless($actor->hasPermission('collections.drive') && $request->header('X-Driver-Actor') === $actor->id, 403);
            $query->where('pickup_tasks.driver_id', $actor->id);
        }
        if (!empty($input['history'])) {
            $query->whereNotIn('pickup_tasks.status', CollectionData::OPEN);
        }
        if (isset($input['service_date']) || empty($input['history'])) {
            $query->where('pickup_tasks.service_date', $input['service_date'] ?? BusinessDate::today());
        }

        return ApiPage::response($query->orderByDesc('pickup_tasks.service_date')->orderBy('pickup_tasks.slot')->orderBy('pickup_tasks.id'), $input);
    }

    public function customerHistory(Request $request, string $customer, CustomerData $customers): JsonResponse
    {
        $customers->find($customer);
        $query = $this->data->visibleTasks($request->user())->where('pickup_tasks.customer_id', $customer)
            ->whereNotIn('pickup_tasks.status', CollectionData::OPEN)
            ->orderByDesc('pickup_tasks.service_date')->orderByDesc('pickup_tasks.slot')->orderBy('pickup_tasks.id');

        return ApiPage::response($query, $request->validate(ApiPage::rules()));
    }

    public function show(Request $request, string $task): JsonResponse
    {
        return $this->item($this->data->visibleTask($request->user(), $task));
    }

    public function createTask(CollectionRequest $request): JsonResponse
    {
        return $this->item($this->workflow->manual($request->user(), $request->validated()), 201);
    }

    public function generate(CollectionRequest $request, GeneratePickups $generator): JsonResponse
    {
        $date = $request->validated('service_date');

        return $this->item(['created' => $generator->forDate($date), 'service_date' => $date]);
    }

    public function assign(CollectionRequest $request, string $task): JsonResponse
    {
        return $this->item($this->workflow->assign($request->user(), $task, $request->validated()));
    }

    public function cancel(CollectionRequest $request, string $task): JsonResponse
    {
        return $this->item($this->workflow->cancel($request->user(), $task, $request->validated()));
    }

    public function events(Request $request, string $task): JsonResponse
    {
        $this->data->visibleTask($request->user(), $task);
        $query = DB::table('pickup_events as e')->leftJoin('pickup_waste_entries as w', 'w.event_id', '=', 'e.id')
            ->where('e.task_id', $task)->orderBy('e.sequence')->select([
                'e.id', 'e.sequence', 'e.type', 'e.reason', 'e.captured_at', 'e.received_at',
                'e.latitude', 'e.longitude', 'e.accuracy_m', 'w.weight_kg', 'w.weight_basis',
            ]);

        return ApiPage::response($query, $request->validate(ApiPage::rules()), function (object $event): object {
            $event->captured_at = CarbonImmutable::parse($event->captured_at)->toISOString();
            $event->received_at = CarbonImmutable::parse($event->received_at)->toISOString();
            $event->evidence_ids = DB::table('pickup_evidence')->where('event_id', $event->id)->orderBy('id')->pluck('id');

            return $event;
        });
    }

    public function addEvent(CollectionRequest $request, string $task): JsonResponse
    {
        return $this->item($this->workflow->driverEvent($request->user(), $task, $request->validated()));
    }

    public function upload(CollectionRequest $request, string $task): JsonResponse
    {
        $file = $this->photos->stage($request->user(), $task, $request->validated('client_file_id'), $request->file('photo'));

        return $this->item(['id' => $file->id, 'status' => 'accepted'], 201);
    }

    public function evidence(Request $request, string $task, string $file): JsonResponse
    {
        $photo = $this->photos->visible($request->user(), $task, $file);

        return $this->item(['id' => $photo->id, 'status' => 'accepted']);
    }

    public function download(Request $request, string $task, string $file): StreamedResponse
    {
        $photo = $this->photos->visible($request->user(), $task, $file);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($photo->object_key), 404);

        return $disk->response($photo->object_key, 'foto-pengambilan.jpg', [
            'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store',
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $input = $request->validate(ApiPage::rules() + ['active' => ['sometimes', 'boolean']]);
        $query = DB::table('vehicles')->orderBy('registration')->orderBy('id');
        if (isset($input['active'])) {
            $query->where('active', (bool) $input['active']);
        }

        return ApiPage::response($query, $input);
    }

    public function drivers(Request $request): JsonResponse
    {
        $input = $request->validate(ApiPage::rules());
        $query = DB::table('users')->select('id', 'name')->where('active', true)
            ->whereExists(function ($subquery): void {
                $subquery->selectRaw('1')->from('user_roles as ur')->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
                    ->join('permissions as p', 'p.id', '=', 'rp.permission_id')->whereColumn('ur.user_id', 'users.id')->where('p.code', 'collections.drive');
            })->orderBy('name')->orderBy('id');

        return ApiPage::response($query, $input);
    }

    public function createVehicle(CollectionRequest $request, AuditLog $audit): JsonResponse
    {
        $id = Str::uuid()->toString();
        $vehicle = $this->saveVehicle($request, $audit, $id, true);

        return $this->item($vehicle, 201);
    }

    public function updateVehicle(CollectionRequest $request, string $vehicle, AuditLog $audit): JsonResponse
    {
        return $this->item($this->saveVehicle($request, $audit, $vehicle, false));
    }

    private function saveVehicle(CollectionRequest $request, AuditLog $audit, string $id, bool $new): object
    {
        try {
            return DB::transaction(function () use ($request, $audit, $id, $new): object {
                $query = DB::table('vehicles')->where('id', $id);
                $fields = $request->validated();
                if (isset($fields['active'])) {
                    $fields['active'] = (bool) $fields['active'];
                }
                if ($new) {
                    DB::table('vehicles')->insert($fields + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    abort_if((clone $query)->lockForUpdate()->first() === null, 404);
                    if (($fields['active'] ?? true) === false) {
                        abort_if(DB::table('pickup_tasks')->where('vehicle_id', $id)->whereIn('status', CollectionData::OPEN)->exists(), 409);
                    }
                    $query->update($fields + ['updated_at' => now()]);
                }
                $audit->record($request->user()->id, 'collection.vehicle_saved', 'vehicle', $id, ['fields' => array_keys($fields)]);

                return $query->first();
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() === '23505') {
                throw ValidationException::withMessages(['registration' => 'Nomor polisi sudah terdaftar.']);
            }

            throw $error;
        }
    }

    private function item(object|array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}
