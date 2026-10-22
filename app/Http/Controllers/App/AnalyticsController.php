<?php

namespace App\Http\Controllers\App;

use App\Services\AnalyticsReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsReport $report): Response
    {
        $days = in_array((int) $request->input('days'), [7, 30, 90], true) ? (int) $request->input('days') : 30;
        $configurator = $request->integer('configurator') ?: null;
        $shop = $this->shop();

        return Inertia::render('Analytics', [
            'level' => $shop->plan?->feature('analytics', 'none'),
            'report' => $shop->plan?->feature('analytics', 'none') === 'none' ? null : $report->summary($shop, $days, $configurator),
            'configurators' => $shop->configurators()->get(['id', 'name']),
            'filters' => ['days' => $days, 'configurator' => $configurator],
        ]);
    }

    public function export(Request $request, AnalyticsReport $report): StreamedResponse
    {
        abort_unless($this->shop()->plan?->feature('analytics') === 'export', 403, 'CSV export is included on Pro.');
        $data = $report->summary($this->shop(), (int) $request->input('days', 30));

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Configurator', 'Opens', 'Add-to-cart rate %', 'Orders', 'Revenue', 'Avg order']);
            foreach ($data['by_configurator'] as $row) {
                fputcsv($out, [$row['name'], $row['opens'], $row['cart_rate'], $row['orders'], $row['revenue_cents'] / 100, $row['aov_cents'] / 100]);
            }
            fclose($out);
        }, 'composa-analytics.csv', ['Content-Type' => 'text/csv']);
    }
}
