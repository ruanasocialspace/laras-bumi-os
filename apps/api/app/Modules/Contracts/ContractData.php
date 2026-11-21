<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use App\Modules\SharedInfrastructure\BusinessDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ContractData
{
    public function find(string $id, bool $lock = false): object
    {
        $query = DB::table('contracts')->where('id', $id);

        return ($lock ? $query->lockForUpdate() : $query)->first() ?? throw new NotFoundHttpException();
    }

    public function service(string $contractId, string $id): object
    {
        return $this->services($contractId)->where('id', $id)->first() ?? throw new NotFoundHttpException();
    }

    public function services(string $contractId): Builder
    {
        return DB::table('contract_services')->where('contract_id', $contractId);
    }

    public function schedules(string $contractId): Builder
    {
        return DB::table('pickup_schedules')->whereIn('service_id', $this->services($contractId)->select('id'));
    }

    public function rateOn(string $serviceId, string $date): ?object
    {
        return DB::table('service_rates')->where('service_id', $serviceId)->where('effective_from', '<=', $date)
            ->where(fn ($range) => $range->whereNull('effective_until')->orWhere('effective_until', '>', $date))->first();
    }

    public function withEffectiveStatus(object $contract): object
    {
        $contract->effective_status = $this->expired($contract) ? 'expired' : $contract->status;

        return $contract;
    }

    public function expired(object $contract): bool
    {
        return in_array($contract->status, ['active', 'suspended'], true)
            && $contract->ends_on !== null && $contract->ends_on < BusinessDate::today();
    }
}
