<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\SharedInfrastructure\BusinessDate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CustomerData
{
    public function find(string $id, bool $lock = false): object
    {
        $query = DB::table('customers')->where('id', $id);
        $customer = ($lock ? $query->lockForUpdate() : $query)->first();

        return $customer ?? throw new NotFoundHttpException();
    }

    public function location(string $customerId, string $id): object
    {
        return DB::table('pickup_locations')->where('customer_id', $customerId)->where('id', $id)->first()
            ?? throw new NotFoundHttpException();
    }

    public function hasOpenContracts(string $id, bool $includeDraft): bool
    {
        $query = DB::table('contracts')->where('customer_id', $id);
        $today = BusinessDate::today();

        return $query->where(function ($scope) use ($today, $includeDraft): void {
            $scope->where(function ($signed) use ($today): void {
                $signed->whereIn('status', ['active', 'suspended'])
                    ->where(fn ($dates) => $dates->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
            });

            if ($includeDraft) {
                $scope->orWhere('status', 'draft');
            }
        })->exists();
    }
}
