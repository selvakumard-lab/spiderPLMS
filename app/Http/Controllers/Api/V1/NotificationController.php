<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in person's web notifications (team members and clients alike).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'data' => $user->notifications()->latest()->limit(50)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? 'update',
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? null,
                'project_id' => $n->data['project_id'] ?? null,
                'project_name' => $n->data['project_name'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function markRead(Request $request, ?string $notification = null): JsonResponse
    {
        $query = $request->user()->unreadNotifications();

        if ($notification !== null) {
            $query->whereKey($notification);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }
}
