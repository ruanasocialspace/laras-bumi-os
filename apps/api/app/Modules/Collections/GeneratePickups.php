<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use App\Modules\Contracts\ContractData;
use App\Modules\Customers\CustomerData;
use App\Modules\SharedInfrastructure\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GeneratePickups
{
    public function __construct(
        private readonly ContractData $contracts,
        private readonly CustomerData $customers,
        private readonly PickupWorkflow $workflow,
    ) {
    }

    public function forDate(string $date): int
    {
        if ($date < BusinessDate::today()) {
            throw ValidationException::withMessages(['service_date' => 'Tanggal pembuatan tidak boleh di masa lalu.']);
        }

        $created = 0;
        foreach ($this->eligible($date)->select('s.id', 'c.id as contract_id', 'c.customer_id')->cursor() as $candidate) {
            $created += (int) DB::transaction(function () use ($candidate, $date): bool {
                $customer = $this->customers->find($candidate->customer_id, lock: true);
                $this->contracts->find($candidate->contract_id, lock: true);
                $schedule = $this->eligible($date)->where('s.id', $candidate->id)
                    ->select('s.*', 'cs.location_id')->first();

                if ($customer->status !== 'active' || $schedule === null || $this->contracts->rateOn($schedule->service_id, $date) === null) {
                    return false;
                }

                $location = $this->customers->location($customer->id, $schedule->location_id);

                return $this->workflow->createTask(Str::uuid()->toString(), $location, $date, $schedule->slot, null, $schedule);
            }, 3);
        }

        return $created;
    }

    private function eligible(string $date): Builder
    {
        return DB::table('pickup_schedules as s')
            ->join('contract_services as cs', 'cs.id', '=', 's.service_id')->join('contracts as c', 'c.id', '=', 'cs.contract_id')
            ->where('c.status', 'active')->where('c.starts_on', '<=', $date)
            ->where(fn ($window) => $window->whereNull('c.ends_on')->orWhere('c.ends_on', '>=', $date))
            ->where('s.weekday', CarbonImmutable::parse($date, 'Asia/Jakarta')->dayOfWeekIso)
            ->where('s.effective_from', '<=', $date)
            ->where(fn ($window) => $window->whereNull('s.effective_until')->orWhere('s.effective_until', '>', $date))
            ->orderBy('s.id');
    }
}
