<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Permission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['code', 'name'];
}
