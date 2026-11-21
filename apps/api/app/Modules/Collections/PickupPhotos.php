<?php

declare(strict_types=1);

namespace App\Modules\Collections;

use App\Modules\IdentityAccess\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PickupPhotos
{
    public function __construct(private readonly CollectionData $data)
    {
    }

    public function stage(User $actor, string $taskId, string $clientId, UploadedFile $photo): object
    {
        $task = $this->data->task($taskId);
        abort_unless($task->driver_id === $actor->id, 404);
        $original = $photo->get();
        $hash = hash('sha256', $original);
        $clientId = strtolower($clientId);
        $existing = DB::table('pickup_evidence')->where('id', $clientId)->first();
        $jpeg = $existing === null ? $this->sanitize($original) : null;

        return DB::transaction(function () use ($actor, $taskId, $clientId, $hash, $jpeg): object {
            $task = $this->data->task($taskId, lock: true);
            abort_unless($task->driver_id === $actor->id, 404);
            $existing = DB::table('pickup_evidence')->where('id', $clientId)->first();

            if ($existing !== null) {
                abort_unless($existing->task_id === $task->id && $existing->uploader_id === $actor->id
                    && hash_equals($existing->original_hash, $hash), 409);

                return $existing;
            }

            abort_unless($task->status === 'arrived', 409);
            $key = "pickup/$task->id/$clientId.jpg";
            $size = strlen($jpeg);
            $saved = DB::table('pickup_evidence')->insertOrIgnore([
                'id' => $clientId, 'task_id' => $task->id, 'uploader_id' => $actor->id,
                'original_hash' => $hash, 'object_key' => $key, 'bytes' => $size, 'created_at' => now(),
            ]);
            abort_unless($saved === 1, 409);
            abort_unless(Storage::disk('local')->put($key, $jpeg), 503);

            return DB::table('pickup_evidence')->where('id', $clientId)->first();
        }, 3);
    }

    public function visible(User $actor, string $taskId, string $fileId): object
    {
        $task = $this->data->visibleTask($actor, $taskId);
        $query = DB::table('pickup_evidence')->where('task_id', $task->id)->where('id', $fileId);
        if (!$actor->hasPermission('collections.read')) {
            $query->where('uploader_id', $actor->id);
        } else {
            $query->whereNotNull('event_id');
        }
        $file = $query->first();
        abort_if($file === null, 404);

        return $file;
    }

    private function sanitize(string $original): string
    {
        $size = @getimagesizefromstring($original);
        if ($size === false || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            || $size[0] * $size[1] > 16000000) {
            throw ValidationException::withMessages(['photo' => 'Gunakan foto JPEG atau PNG hingga 16 megapiksel.']);
        }

        $image = @imagecreatefromstring($original);
        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'Foto tidak dapat dibaca.']);
        }

        $scale = min(1, 2048 / max($size[0], $size[1]));
        $width = max(1, (int) round($scale * $size[0]));
        $height = max(1, (int) round($scale * $size[1]));
        $output = imagecreatetruecolor($width, $height);
        abort_if($output === false, 503);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start();

        try {
            abort_unless(imagejpeg($output, null, 85), 503);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
