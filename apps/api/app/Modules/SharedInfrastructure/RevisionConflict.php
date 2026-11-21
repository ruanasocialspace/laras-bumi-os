<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RevisionConflict extends ConflictHttpException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct();
    }
}
