<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use App\Modules\IdentityAccess\User;
use App\Modules\SharedInfrastructure\BusinessDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CollectionData
{
    public const OPEN = ['scheduled', 'assigned', 'en_route', 'arrived'];

    public function task(string $id, bool $lock = false): object
    {
        $query = DB::table('pickup_tasks')->where('id', $id);

        return ($lock ? $query->lockForUpdate() : $query)->first() ?? throw new NotFoundHttpException();
    }

    public function visibleTask(User $actor, string $id): object
    {
        return $this->visibleTasks($actor)->where('pickup_tasks.id', $id)->first() ?? throw new NotFoundHttpException();
    }

    public function visibleTasks(User $actor): Builder
    {
        $query = DB::table('pickup_tasks')
            ->leftJoin('users as driver', 'driver.id', '=', 'pickup_tasks.driver_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'pickup_tasks.vehicle_id')
            ->join('customers', 'customers.id', '=', 'pickup_tasks.customer_id')
            ->select([
                'pickup_tasks.id', 'pickup_tasks.customer_id', 'customers.name as customer_name',
                'pickup_tasks.location_id', 'pickup_tasks.service_id', 'pickup_tasks.schedule_id',
                'pickup_tasks.service_date', 'pickup_tasks.slot', 'pickup_tasks.status', 'pickup_tasks.revision',
                'pickup_tasks.location_name', 'pickup_tasks.address', 'pickup_tasks.latitude', 'pickup_tasks.longitude',
                'pickup_tasks.driver_id', 'driver.name as driver_name', 'pickup_tasks.vehicle_id', 'vehicles.registration as vehicle_registration',
            ]);

        if (!$actor->hasPermission('collections.read')) {
            return $query->where('pickup_tasks.driver_id', $actor->id);
        }

        return $query->leftJoin('contract_services as cs', 'cs.id', '=', 'pickup_tasks.service_id')
            ->leftJoin('contracts as c', 'c.id', '=', 'cs.contract_id')
            ->selectRaw(
                "case when c.status in ('active', 'suspended') and c.ends_on < ? then 'expired' else c.status end as contract_status",
                [BusinessDate::today()]
            );
    }

    public function hasOpenForCustomer(string $customer): bool
    {
        return DB::table('pickup_tasks')->where('customer_id', $customer)->whereIn('status', self::OPEN)->exists();
    }
}
