<?php

namespace App\Http\Controllers\App;

use App\Jobs\GenerateProductionPdf;
use App\Models\ConfiguredOrder;
use App\Services\SpecSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public const STATUSES = ['new', 'in_production', 'ready', 'shipped'];

    public function index(Request $request): Response
    {
        $shop = $this->shop();
        $query = $shop->configuredOrders()->with(['configurator:id,name', 'configuration:id,image_url'])->latest('placed_at');
        if ($status = $request->string('status')->toString()) {
            $status === 'flagged' ? $query->where('price_check', '!=', 'ok') : $query->where('production_status', $status);
        }
        if ($q = trim($request->string('q')->toString())) {
            $query->where(fn ($w) => $w->where('order_name', 'ilike', "%{$q}%")->orWhere('customer_name', 'ilike', "%{$q}%"));
        }
        $orders = $query->paginate(25)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders->through(fn (ConfiguredOrder $o) => [
                'id' => $o->id, 'order' => $o->order_name ?? $o->order_id, 'customer' => $o->customer_name,
                'configurator' => $o->configurator?->name, 'total_cents' => $o->line_total_cents, 'currency' => $o->currency,
                'status' => $o->production_status, 'price_check' => $o->price_check, 'placed_at' => $o->placed_at?->toIso8601String(),
                'image' => $o->configuration?->image_url,
            ]),
            'filters' => ['status' => $request->input('status'), 'q' => $request->input('q')],
            'counts' => ['all' => $shop->configuredOrders()->count(), 'flagged' => $shop->configuredOrders()->where('price_check', '!=', 'ok')->count()] + collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => $shop->configuredOrders()->where('production_status', $s)->count()])->all(),
        ]);
    }

    public function show(int $order): JsonResponse
    {
        $o = $this->shop()->configuredOrders()->with(['configuration.version', 'configuration.uploads', 'configurator'])->findOrFail($order);
        $c = $o->configuration;

        return response()->json([
            'id' => $o->id, 'order' => $o->order_name ?? $o->order_id, 'order_id' => $o->order_id, 'status' => $o->production_status,
            'price_check' => $o->price_check, 'quantity' => $o->quantity, 'total_cents' => $o->line_total_cents, 'currency' => $o->currency,
            'configurator' => $o->configurator?->name, 'version' => $c?->version?->version, 'config_id' => $c?->id,
            'signed_cents' => $c?->price_cents, 'image' => $c?->image_url, 'cart_mode' => $this->shop()->cart_mode,
            'rows' => $c ? SpecSheet::rows($c->version?->definition ?? [], $c->answers ?? []) : [],
            'files' => $c ? $c->uploads->where('status', '!=', 'deleted')->map(fn ($u) => ['id' => $u->id, 'name' => $u->original_name, 'size' => $u->size])->values() : [],
            'pdf' => (bool) $o->spec_pdf_path,
            'admin_url' => 'https://'.$this->shop()->domain.'/admin/orders/'.basename(str_replace('gid://shopify/Order/', '', $o->order_id)),
        ]);
    }

    public function updateStatus(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:'.implode(',', self::STATUSES)]);
        $o = $this->shop()->configuredOrders()->findOrFail($order);
        $o->forceFill(['production_status' => $data['status']])->save();

        return response()->json(['status' => $o->production_status]);
    }

    /** Production PDF, streamed from the private bucket (generated on first request if needed). */
    public function pdf(int $order): StreamedResponse|JsonResponse
    {
        $o = $this->shop()->configuredOrders()->findOrFail($order);
        if (! $this->shop()->plan?->feature('pdf')) {
            return response()->json(['message' => 'Production PDFs are included from the Starter plan.'], 403);
        }
        if (! $o->spec_pdf_path) {
            GenerateProductionPdf::dispatchSync($o->id);
            $o->refresh();
        }

        return Storage::disk(config('composa.uploads.disk'))->download($o->spec_pdf_path, 'spec-'.($o->order_name ?? $o->id).'.pdf');
    }

    /** Short-lived link to a shopper upload. */
    public function file(int $order, string $upload): JsonResponse
    {
        $o = $this->shop()->configuredOrders()->with('configuration.uploads')->findOrFail($order);
        $file = $o->configuration?->uploads->firstWhere('id', $upload) ?? abort(404);
        $url = Storage::disk(config('composa.uploads.disk'))->temporaryUrl($file->path, now()->addMinutes(config('composa.uploads.get_ttl_minutes')));

        return response()->json(['url' => $url]);
    }
}
