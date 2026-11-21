<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CollectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (in_array($this->route()->getActionMethod(), ['addEvent', 'upload'], true)) {
            abort_unless($this->header('X-Driver-Actor') === $this->user()->id, 403);
        }
        if (is_string($this->input('registration'))) {
            $this->merge(['registration' => strtoupper($this->input('registration'))]);
        }
    }

    public function rules(): array
    {
        $vehicle = [
            'name' => ['required', 'string', 'max:120'],
            'registration' => ['required', 'string', 'regex:/\A[A-Z0-9][A-Z0-9 -]{1,23}\z/'],
            'active' => ['sometimes', 'required', 'boolean'],
        ];

        return match ($this->route()->getActionMethod()) {
            'createVehicle' => $vehicle,
            'updateVehicle' => array_map(fn (array $rules) => array_values(array_unique(['sometimes', ...$rules])), $vehicle),
            'createTask' => [
                'client_task_id' => ['required', 'uuid'],
                'customer_id' => ['required', 'uuid'],
                'location_id' => ['required', 'uuid'],
                'service_date' => ['required', 'date_format:Y-m-d'],
                'slot' => ['required', 'date_format:H:i'],
            ],
            'generate' => ['service_date' => ['required', 'date_format:Y-m-d']],
            'assign' => [
                'expected_revision' => ['required', 'integer', 'min:0'],
                'driver_id' => ['required', 'uuid'],
                'vehicle_id' => ['required', 'uuid'],
            ],
            'cancel' => ['expected_revision' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:500']],
            'addEvent' => $this->eventRules(),
            'upload' => [
                'client_file_id' => ['required', 'uuid'],
                'photo' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png'],
            ],
        };
    }

    private function eventRules(): array
    {
        return [
            'client_event_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(['en_route', 'arrived', 'completed', 'failed'])],
            'captured_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'reason' => ['exclude_unless:type,failed', 'required', 'string', 'max:500'],
            'latitude' => ['exclude_unless:type,completed', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:type,completed', 'required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['exclude_unless:type,completed', 'required', 'numeric', 'between:0,10000'],
            'evidence_ids' => ['exclude_unless:type,completed', 'required', 'array', 'list', 'min:1', 'max:3'],
            'evidence_ids.*' => ['uuid', 'distinct'],
            'weight_kg' => ['exclude_unless:type,completed', 'required', 'string', 'regex:/\A[0-9]{1,9}(?:\.[0-9]{1,3})?\z/', 'numeric', 'gt:0'],
            'weight_basis' => ['exclude_unless:type,completed', 'required', Rule::in(['estimated', 'field_measured'])],
        ];
    }
}
