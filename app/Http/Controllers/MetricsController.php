<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{

    private function resolveCacheTtl(string $bucket, string $period): int
    {
        $bucket = strtolower($bucket);
        $periodKey = strtoupper($period);

        $envKey = sprintf('METRICS_CACHE_TTL_%s_%s', strtoupper($bucket), $periodKey);
        $byBucketAndPeriod = env($envKey);
        if ($byBucketAndPeriod !== null) {
            return (int) $byBucketAndPeriod;
        }

        $byBucketDefault = env('METRICS_CACHE_TTL_' . strtoupper($bucket));
        if ($byBucketDefault !== null) {
            return (int) $byBucketDefault;
        }

        $globalDefault = env('METRICS_CACHE_TTL_DEFAULT');
        if ($globalDefault !== null) {
            return (int) $globalDefault;
        }

        $defaults = [
            'summary' => [
                'TODAY' => 15,
                '7D' => 60,
                '30D' => 120,
                'ALL' => 300,
            ],
            'top' => [
                'TODAY' => 15,
                '7D' => 60,
                '30D' => 120,
                'ALL' => 300,
            ],
        ];

        return $defaults[$bucket][strtoupper($period)] ?? 30;
    }

    public function summary(Request $request)
    {
        $period = $request->query('period', '7d');
        [$from, $to] = $this->parsePeriod($period);

        $userId = Auth::id();

        $cacheKey = sprintf('metrics:summary:%d:%s:%s', $userId, optional($from)->timestamp ?? 0, optional($to)->timestamp ?? 0);
        $ttl = $this->resolveCacheTtl('summary', $period);

        $data = Cache::remember($cacheKey, $ttl, function () use ($userId, $from, $to, $period) {
            $statusAgg = Link::query()
                ->where('user_id', $userId)
                ->selectRaw('COUNT(*) as total_links')
                ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_links")
                ->selectRaw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_links")
                ->first();

            if ($period === 'all') {
                $totalClicksInPeriod = (int) Link::where('user_id', $userId)->sum('click_count');
            } else {
                $fromDay = $from->toDateString();
                $toDay = $to->toDateString();

                $aggBase = DB::table('visit_aggregates')
                    ->join('links', 'links.id', '=', 'visit_aggregates.link_id')
                    ->where('links.user_id', $userId)
                    ->whereBetween('visit_aggregates.day', [$fromDay, $toDay]);

                $hasAggregateRows = (clone $aggBase)->exists();
                $totalClicksInPeriod = (int) (clone $aggBase)->sum('visit_aggregates.clicks');

                if (!$hasAggregateRows) {
                    $totalClicksInPeriod = (int) DB::table('visits')
                        ->join('links', 'links.id', '=', 'visits.link_id')
                        ->where('links.user_id', $userId)
                        ->whereBetween('visits.created_at', [$from, $to])
                        ->count('visits.id');
                }
            }

            return [
                'total_links' => (int) ($statusAgg->total_links ?? 0),
                'active_links' => (int) ($statusAgg->active_links ?? 0),
                'expired_links' => (int) ($statusAgg->expired_links ?? 0),
                'total_clicks_in_period' => $totalClicksInPeriod,
            ];
        });

        return response()->json([
            'total_links' => $data['total_links'],
            'active_links' => $data['active_links'],
            'expired_links' => $data['expired_links'],
            'total_clicks_in_period' => $data['total_clicks_in_period'],
            'period' => [
                'from' => optional($from)->toIso8601String(),
                'to' => optional($to)->toIso8601String(),
            ],
        ])->header('Cache-Control', 'public, max-age=' . $ttl . ', s-maxage=' . $ttl);
    }

    public function top(Request $request)
    {
        $period = $request->query('period', '7d');
        [$from, $to] = $this->parsePeriod($period);
        $userId = Auth::id();

        $cacheKey = sprintf('metrics:top:%d:%s:%s', $userId, optional($from)->timestamp ?? 0, optional($to)->timestamp ?? 0);
        $ttl = $this->resolveCacheTtl('top', $period);

        $data = Cache::remember($cacheKey, $ttl, function () use ($userId, $from, $to, $period) {
            if ($period === 'all') {
                return Link::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('click_count')
                    ->limit(10)
                    ->get(['id', 'slug', 'original_url', 'click_count'])
                    ->map(function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'slug' => $row->slug,
                            'original_url' => $row->original_url,
                            'clicks' => (int) $row->click_count,
                        ];
                    })->values();
            }

            $fromDay = $from->toDateString();
            $toDay = $to->toDateString();

            $rows = DB::table('visit_aggregates')
                ->join('links', 'links.id', '=', 'visit_aggregates.link_id')
                ->where('links.user_id', $userId)
                ->whereBetween('visit_aggregates.day', [$fromDay, $toDay])
                ->groupBy('links.id', 'links.slug', 'links.original_url')
                ->orderByDesc('clicks')
                ->limit(10)
                ->get([
                    'links.id as id',
                    'links.slug as slug',
                    'links.original_url as original_url',
                    DB::raw('SUM(visit_aggregates.clicks) as clicks'),
                ]);

            if ($rows->isEmpty()) {
                $visitsAgg = DB::table('visits')
                    ->join('links', 'links.id', '=', 'visits.link_id')
                    ->where('links.user_id', $userId)
                    ->whereBetween('visits.created_at', [$from, $to])
                    ->groupBy('visits.link_id')
                    ->select('visits.link_id', DB::raw('COUNT(*) as clicks'))
                    ->orderByDesc('clicks')
                    ->limit(10);

                $rows = DB::query()
                    ->fromSub($visitsAgg, 'va')
                    ->join('links', 'links.id', '=', 'va.link_id')
                    ->orderByDesc('va.clicks')
                    ->get([
                        'links.id as id',
                        'links.slug as slug',
                        'links.original_url as original_url',
                        'va.clicks as clicks',
                    ]);
            }

            return $rows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'slug' => $row->slug,
                    'original_url' => $row->original_url,
                    'clicks' => (int) $row->clicks,
                ];
            })->values();
        });

        return response()->json([
            'top' => $data,
        ])->header('Cache-Control', 'public, max-age=' . $ttl . ', s-maxage=' . $ttl);
    }

    private function parsePeriod(string $period): array
    {
        $now = now();
        $to = $now;
        $from = null;

        switch ($period) {
            case 'today':
                $from = $now->copy()->startOfDay();
                break;
            case '7d':
                $from = $now->copy()->subDays(7);
                break;
            case '30d':
                $from = $now->copy()->subDays(30);
                break;
            default:
                $from = null;
                break;
        }

        return [$from, $to];
    }
}


