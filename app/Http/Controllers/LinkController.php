<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;

class LinkController extends Controller
{
    public function index(): View
    {
        $newLink = null;
        $newLinkId = session('new_link_id');
        if ($newLinkId) {
            $newLink = Link::where('user_id', Auth::id())->find($newLinkId);
        }
        return view('links.index', compact('newLink'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'original_url' => ['required', 'url', 'max:2048'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $slug = $this->generateUniqueSlug();

        $expiresAt = null;
        if (!empty($validated['expires_in_minutes'])) {
            $expiresAt = now()->addMinutes((int) $validated['expires_in_minutes']);
        }

        $link = Link::create([
            'user_id' => Auth::id(),
            'original_url' => $validated['original_url'],
            'slug' => $slug,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        return Redirect::route('links.index')
            ->with('status', 'Link criado com sucesso!')
            ->with('new_link_id', $link->id);
    }

    public function apiIndex(Request $request)
    {
        $links = Link::where('user_id', Auth::id())->latest()->paginate(10);
        return response()->json($links);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'original_url' => ['required', 'url', 'max:2048'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $slug = $this->generateUniqueSlug();
        $expiresAt = null;
        if (!empty($validated['expires_in_minutes'])) {
            $expiresAt = now()->addMinutes((int) $validated['expires_in_minutes']);
        }

        $link = Link::create([
            'user_id' => Auth::id(),
            'original_url' => $validated['original_url'],
            'slug' => $slug,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        return response()->json($link, 201);
    }

    public function apiShow(Link $link)
    {
        abort_if($link->user_id !== Auth::id(), 403);
        return response()->json($link);
    }

    public function destroy(Link $link): RedirectResponse
    {
        abort_if($link->user_id !== Auth::id(), 403);
        $link->delete();
        return Redirect::back()->with('status', 'Link removido.');
    }

    public function redirect(string $slug)
    {
        $link = Link::where('slug', $slug)->firstOrFail();

        if ($link->expires_at && now()->greaterThan($link->expires_at)) {
            $link->update(['status' => 'expired']);
            return response()->view('links.expired', ['link' => $link], 410);
        }

        if ($link->status !== 'active') {
            return response()->view('links.inactive', ['link' => $link], 410);
        }

        $link->increment('click_count');

        Visit::create([
            'link_id' => $link->id,
            'ip_hash' => hash('sha256', request()->ip() ?? 'unknown'),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ]);

        try {
            $today = now()->toDateString();
            DB::table('visit_aggregates')->updateOrInsert(
                ['link_id' => $link->id, 'day' => $today],
                ['clicks' => DB::raw('clicks + 1'), 'updated_at' => now(), 'created_at' => now()]
            );
        } catch (\Throwable $e) {
        }

        return redirect()->away($link->original_url);
    }

    public function expire(Link $link)
    {
        abort_if($link->user_id !== Auth::id(), 403);

        if ($link->expires_at && now()->greaterThanOrEqualTo($link->expires_at)) {
            $link->update(['status' => 'expired']);
        }

        return response()->json([
            'status' => $link->status,
        ]);
    }

    public function qrcode(Link $link)
    {
        abort_if($link->user_id !== Auth::id(), 403);
        $shortUrl = url('/s/'.$link->slug);
        $svg = QrCode::format('svg')->size(256)->generate($shortUrl);
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = rtrim(strtr(base64_encode(random_bytes(6)), '+/', '-_'), '=');
        } while (Link::where('slug', $slug)->exists());

        return $slug;
    }

    public function poll(Request $request)
    {
        $links = Link::query()
            ->where('user_id', Auth::id())
            ->latest('id')
            ->take(10)
            ->get(['id', 'slug', 'status', 'click_count', 'expires_at']);

        return response()->json([
            'links' => $links->map(function (Link $link) {
                return [
                    'id' => $link->id,
                    'slug' => $link->slug,
                    'status' => $link->status,
                    'click_count' => $link->click_count,
                    'expires_at' => optional($link->expires_at)->toIso8601String(),
                ];
            }),
        ])->header('Cache-Control', 'public, max-age=5, s-maxage=5');
    }
}


