<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function summary(Request $request)
    {
        [$from, $to] = $this->parsePeriod($request->query('period', '7d'));

        $userId = Auth::id();

        $totalLinks = Link::where('user_id', $userId)->count();
        $activeLinks = Link::where('user_id', $userId)->where('status', 'active')->count();
        $expiredLinks = Link::where('user_id', $userId)->where('status', 'expired')->count();

        $totalClicksInPeriod = Visit::whereHas('link', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->when($from, function ($q) use ($from) {
                $q->where('created_at', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->where('created_at', '<=', $to);
            })
            ->count();

        return response()->json([
            'total_links' => $totalLinks,
            'active_links' => $activeLinks,
            'expired_links' => $expiredLinks,
            'total_clicks_in_period' => $totalClicksInPeriod,
            'period' => [
                'from' => optional($from)->toIso8601String(),
                'to' => optional($to)->toIso8601String(),
            ],
        ]);
    }

    public function top(Request $request)
    {
        [$from, $to] = $this->parsePeriod($request->query('period', '7d'));
        $userId = Auth::id();

        $query = Visit::query()
            ->select(['link_id', DB::raw('COUNT(*) as clicks')])
            ->when($from, function ($q) use ($from) {
                $q->where('created_at', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->where('created_at', '<=', $to);
            })
            ->whereHas('link', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->groupBy('link_id')
            ->orderByDesc('clicks')
            ->limit(10);

        $rows = $query->get();

        $linksById = Link::whereIn('id', $rows->pluck('link_id'))
            ->get(['id', 'slug', 'original_url'])
            ->keyBy('id');

        $data = $rows->map(function ($row) use ($linksById) {
            $link = $linksById->get($row->link_id);
            return [
                'id' => $row->link_id,
                'slug' => $link?->slug,
                'original_url' => $link?->original_url,
                'clicks' => (int) $row->clicks,
            ];
        })->values();

        return response()->json([
            'top' => $data,
        ]);
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


