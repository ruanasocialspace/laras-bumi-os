<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $code = ['required', 'string', 'regex:/\A[A-Z0-9][A-Z0-9_-]{0,31}\z/'];
        $customerFields = [
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'uuid', Rule::exists('customer_categories', 'id')],
        ];

        return match ($this->route()->getActionMethod()) {
            'store' => $customerFields + ['code' => [...$code, Rule::unique('customers')]],
            'update' => ['status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])]]
                + array_map(fn (array $rules) => ['sometimes', ...$rules], $customerFields),
            'createCategory' => [
                'code' => [...$code, Rule::unique('customer_categories')],
                'name' => ['required', 'string', 'max:120'],
            ],
            'saveContact' => [
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
                'email' => ['nullable', 'required_without:phone', 'email', 'max:254'],
            ],
            'updateContact' => [
                'name' => ['sometimes', 'required', 'string', 'max:120'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
                'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            ],
            'saveLocation' => [
                'name' => ['required', 'string', 'max:120'],
                'address' => ['required', 'string', 'max:500'],
                'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'decimal:0,6', 'between:-90,90'],
                'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'decimal:0,6', 'between:-180,180'],
            ],
            'updateLocation' => [
                'name' => ['sometimes', 'required', 'string', 'max:120'],
                'address' => ['sometimes', 'required', 'string', 'max:500'],
                'latitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-90,90'],
                'longitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,6', 'between:-180,180'],
            ],
            'saveBilling' => [
                'billing_name' => ['required', 'string', 'max:160'],
                'address' => ['required', 'string', 'max:500'],
                'email' => ['nullable', 'email', 'max:254'],
                'payment_term_days' => ['required', 'integer', 'between:0,365'],
            ],
        };
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }
}
