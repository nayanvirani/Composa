<?php

namespace App\Jobs;

use App\Models\Configuration;
use App\Models\Upload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Daily: expires configurations that never became orders and deletes shopper uploads that were
 * never ordered. Uploads attached to an order are kept for production.
 */
class CleanupStorefrontData implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Configuration::query()->where('status', 'cart')->where('expires_at', '<', now())->update(['status' => 'expired']);

        $disk = Storage::disk(config('composa.uploads.disk'));
        Upload::query()
            ->whereIn('status', ['pending', 'uploaded', 'attached'])
            ->where('created_at', '<', now()->subDays(config('composa.uploads.abandoned_days')))
            ->chunkById(200, function ($uploads) use ($disk) {
                foreach ($uploads as $upload) {
                    try {
                        $disk->delete($upload->path);
                    } catch (\Throwable $e) {
                        report($e);

                        continue;
                    }
                    $upload->forceFill(['status' => 'deleted'])->save();
                }
            });
    }
}
