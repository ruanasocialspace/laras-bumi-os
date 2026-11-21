<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use App\Modules\SharedInfrastructure\ApiPage;
use App\Modules\SharedInfrastructure\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContractController
{
    public function __construct(private readonly ContractData $data, private readonly Contracts $contracts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            ...ApiPage::rules(),
            'q' => ['nullable', 'string', 'max:160'],
            'customer_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'in:draft,active,suspended,expired,terminated'],
        ]);
        $query = DB::table('contracts')->orderByDesc('starts_on')->orderBy('id');
        if (isset($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '\\_%').'%';
            $query->where(fn ($scope) => $scope->where('name', 'ilike', $term)->orWhere('code', 'ilike', $term));
        }
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (isset($filters['status'])) {
            $query->whereRaw(
                "(CASE WHEN status IN ('active', 'suspended') AND ends_on < ? THEN 'expired' ELSE status END) = ?",
                [BusinessDate::today(), $filters['status']],
            );
        }

        return ApiPage::response($query, $filters, $this->data->withEffectiveStatus(...));
    }

    public function show(string $contract): JsonResponse
    {
        return $this->item($this->data->withEffectiveStatus($this->data->find($contract)));
    }

    public function store(ContractRequest $request): JsonResponse
    {
        return $this->item($this->contracts->create($request->user(), $request->validated()), 201);
    }

    public function update(ContractRequest $request, string $contract): JsonResponse
    {
        return $this->item($this->contracts->update($request->user(), $contract, $request->validated()));
    }

    public function transition(ContractRequest $request, string $contract): JsonResponse
    {
        return $this->item($this->contracts->transition($request->user(), $contract, $request->validated()));
    }

    public function history(Request $request, string $contract): JsonResponse
    {
        $this->data->find($contract);
        $query = DB::table('contract_status_events')->where('contract_id', $contract)->orderByDesc('occurred_at')->orderByDesc('id');

        return ApiPage::response($query, $request->validate(ApiPage::rules()), function (object $event): object {
            $event->occurred_at = CarbonImmutable::parse($event->occurred_at)->toISOString();

            return $event;
        });
    }

    public function services(Request $request, string $contract): JsonResponse
    {
        $this->data->find($contract);

        $query = $this->data->services($contract)->join('pickup_locations', 'pickup_locations.id', '=', 'contract_services.location_id')
            ->select('contract_services.*', 'pickup_locations.name as location_name')->orderBy('contract_services.name')->orderBy('contract_services.id');

        return ApiPage::response($query, $request->validate(ApiPage::rules()));
    }

    public function createService(ContractRequest $request, string $contract): JsonResponse
    {
        return $this->item($this->contracts->saveService($request->user(), $contract, $request->validated()), 201);
    }

    public function updateService(ContractRequest $request, string $contract, string $service): JsonResponse
    {
        return $this->item($this->contracts->saveService($request->user(), $contract, $request->validated(), $service));
    }

    public function rates(Request $request, string $contract, string $service): JsonResponse
    {
        $this->data->service($contract, $service);
        $query = DB::table('service_rates')->where('service_id', $service)->orderByDesc('effective_from')->orderBy('id');

        return ApiPage::response($query, $request->validate(ApiPage::rules()));
    }

    public function addRate(ContractRequest $request, string $contract, string $service): JsonResponse
    {
        return $this->item($this->contracts->saveRate($request->user(), $contract, $service, $request->validated()), 201);
    }

    public function updateRate(ContractRequest $request, string $contract, string $service, string $rate): JsonResponse
    {
        return $this->item($this->contracts->saveRate($request->user(), $contract, $service, $request->validated(), $rate));
    }

    public function schedules(Request $request, string $contract): JsonResponse
    {
        $this->data->find($contract);
        $query = $this->data->schedules($contract)->orderBy('weekday')->orderBy('slot')->orderBy('id');
        $filters = $request->validate(ApiPage::rules() + ['service_id' => ['sometimes', 'uuid']]);
        if (isset($filters['service_id'])) {
            $this->data->service($contract, $filters['service_id']);
            $query->where('service_id', $filters['service_id']);
        }

        return ApiPage::response($query, $filters);
    }

    public function createSchedule(ContractRequest $request, string $contract): JsonResponse
    {
        return $this->item($this->contracts->saveSchedule($request->user(), $contract, $request->validated()), 201);
    }

    public function updateSchedule(ContractRequest $request, string $contract, string $schedule): JsonResponse
    {
        return $this->item($this->contracts->saveSchedule($request->user(), $contract, $request->validated(), $schedule));
    }

    private function item(object $item, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $item], $status);
    }
}
