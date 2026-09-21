<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Drives the rail badge on Review Queue. Counted the same way the
            // desk itself counts, so the two can never disagree.
            'pendingReviews' => fn (): int => $request->user() === null
                ? 0
                : ApprovalRequest::query()->where('status', 'pending')->count(),
        ];
    }
}
