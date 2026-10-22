<?php

namespace App\Jobs;

use App\Models\GdprRequest;
use App\Services\GdprProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessGdprRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $requestId) {}

    public function backoff(): array
    {
        return [60, 300, 1800, 7200];
    }

    public function handle(GdprProcessor $processor): void
    {
        $request = GdprRequest::query()->find($this->requestId);
        if (! $request || $request->status === 'done') {
            return;
        }
        // shop/redact must wait until the 48-hour uninstall window has passed.
        if ($request->topic === 'shop/redact' && $request->shop && $request->shop->status === 'active') {
            $request->forceFill(['status' => 'done', 'result' => 'Shop reinstalled; nothing to redact.', 'processed_at' => now()])->save();

            return;
        }
        try {
            $result = $processor->process($request);
            $request->forceFill(['status' => 'done', 'result' => $result, 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            $request->forceFill(['status' => 'failed', 'result' => $e->getMessage()])->save();
            throw $e;
        }
    }
}
