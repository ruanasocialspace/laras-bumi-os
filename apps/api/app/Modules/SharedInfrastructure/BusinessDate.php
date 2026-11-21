<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

final class BusinessDate
{
    public static function today(): string
    {
        return now('Asia/Jakarta')->toDateString();
    }
}
