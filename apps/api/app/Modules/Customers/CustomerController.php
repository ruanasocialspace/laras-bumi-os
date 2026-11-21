<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\SharedInfrastructure\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CustomerController
{
    public function __construct(private readonly CustomerData $data, private readonly Customers $customers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            ...ApiPage::rules(),
            'q' => ['nullable', 'string', 'max:160'],
            'category_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'in:active,inactive,archived'],
        ]);
        $query = DB::table('customers')->orderBy('name')->orderBy('id');
        foreach (['category_id', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '\\_%').'%';
            $query->where(fn ($scope) => $scope->where('name', 'ilike', $term)->orWhere('code', 'ilike', $term));
        }

        return ApiPage::response($query, $filters);
    }

    public function show(string $customer): JsonResponse
    {
        return response()->json(['data' => $this->data->find($customer)]);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->customers->create($request->user(), 'customer', $request->validated())], 201);
    }

    public function update(CustomerRequest $request, string $customer): JsonResponse
    {
        return response()->json(['data' => $this->customers->update($request->user(), $customer, $request->validated())]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => DB::table('customer_categories')->orderBy('name')->orderBy('id')->get()]);
    }

    public function createCategory(CustomerRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->customers->create($request->user(), 'customer_category', $request->validated())], 201);
    }

    public function contacts(string $customer, Request $request): JsonResponse
    {
        return $this->listRelated($customer, 'customer_contacts', $request);
    }

    public function locations(string $customer, Request $request): JsonResponse
    {
        return $this->listRelated($customer, 'pickup_locations', $request);
    }

    public function saveContact(CustomerRequest $request, string $customer): JsonResponse
    {
        return $this->writeRelated($request, $customer, 'customer_contact', null, 201);
    }

    public function updateContact(CustomerRequest $request, string $customer, string $record): JsonResponse
    {
        return $this->writeRelated($request, $customer, 'customer_contact', $record);
    }

    public function saveLocation(CustomerRequest $request, string $customer): JsonResponse
    {
        return $this->writeRelated($request, $customer, 'pickup_location', null, 201);
    }

    public function updateLocation(CustomerRequest $request, string $customer, string $record): JsonResponse
    {
        return $this->writeRelated($request, $customer, 'pickup_location', $record);
    }

    public function billing(string $customer): JsonResponse
    {
        $this->data->find($customer);

        return response()->json(['data' => DB::table('billing_profiles')->where('customer_id', $customer)->first()]);
    }

    public function saveBilling(CustomerRequest $request, string $customer): JsonResponse
    {
        return $this->writeRelated($request, $customer, 'billing_profile', null);
    }

    private function listRelated(string $customer, string $table, Request $request): JsonResponse
    {
        $this->data->find($customer);
        $query = DB::table($table)->where('customer_id', $customer)->orderBy('name')->orderBy('id');

        return ApiPage::response($query, $request->validate(ApiPage::rules()));
    }

    private function writeRelated(CustomerRequest $request, string $customer, string $type, ?string $id, int $status = 200): JsonResponse
    {
        $fields = $request->validated();
        if ($type === 'billing_profile') {
            $fields += ['email' => null];
        }

        return response()->json(['data' => $this->customers->saveRelated($request->user(), $customer, $type, $fields, $id)], $status);
    }
}
