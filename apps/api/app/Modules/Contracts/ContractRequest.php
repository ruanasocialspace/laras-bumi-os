<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ContractRequest extends FormRequest
{
    public function rules(): array
    {
        $contract = [
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
        $dates = [
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after:effective_from'],
        ];

        return match ($this->route()->getActionMethod()) {
            'store' => $contract + [
                'customer_id' => ['required', 'uuid'],
                'code' => ['required', 'string', 'regex:/\A[A-Z0-9][A-Z0-9_-]{0,31}\z/', Rule::unique('contracts')],
            ],
            'update' => array_map(fn (array $rules) => ['sometimes', ...$rules], $contract),
            'transition' => [
                'status' => ['required', 'in:active,suspended,expired,terminated'],
                'reason' => ['nullable', 'required_if:status,suspended,terminated', 'string', 'max:500'],
            ],
            'createService', 'updateService' => [
                'name' => ['required', 'string', 'max:160'],
                'location_id' => ['required', 'uuid'],
            ],
            'addRate', 'updateRate' => $dates + [
                'pricing_model' => ['required', 'in:monthly,per_pickup,per_kg,one_time'],
                'unit_rate' => ['required', 'string', 'regex:/\A[0-9]{1,14}(?:\.[0-9]{1,6})?\z/'],
                'replace_open' => ['sometimes', 'boolean'],
            ],
            'createSchedule', 'updateSchedule' => $dates + [
                'service_id' => ['required', 'uuid'],
                'weekday' => ['required', 'integer', 'between:1,7'],
                'slot' => ['required', 'date_format:H:i'],
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
