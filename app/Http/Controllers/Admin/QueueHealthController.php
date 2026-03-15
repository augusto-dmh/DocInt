<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RabbitMqQueueHealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueHealthController extends Controller
{
    public function __construct(
        public RabbitMqQueueHealthService $queueHealthService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasSuperAdminRole()) {
            abort(403);
        }

        return Inertia::render('admin/QueueHealth', [
            'queueHealth' => $this->queueHealthService->snapshot(),
        ]);
    }
}
