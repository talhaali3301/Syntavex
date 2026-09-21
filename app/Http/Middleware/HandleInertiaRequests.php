<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'pendingReviews' => fn (): int => $request->user() === null
                ? 0
                : ApprovalRequest::query()->where('status', 'pending')->count(),
        ];
    }
}
