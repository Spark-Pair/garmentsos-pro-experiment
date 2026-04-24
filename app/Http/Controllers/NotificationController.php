<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::check()) {
            return response()->json(['data' => []], 401);
        }

        $notifications = Notification::where('recieverId', Auth::id())
            ->orderBy('id')
            ->limit(20)
            ->get();

        $data = $notifications
            ->map(function (Notification $notification) {
                $decoded = json_decode((string) $notification->caption, true);

                if (is_array($decoded)) {
                    return [
                        'notification_id' => $notification->id,
                        'title' => $decoded['title'] ?? $decoded['t'] ?? 'Notification',
                        'message' => $decoded['message'] ?? $decoded['m'] ?? '',
                        'type' => $decoded['type'] ?? $decoded['tp'] ?? 'info',
                        'url' => $decoded['url'] ?? $decoded['u'] ?? null,
                        'persist' => (bool) ($decoded['persist'] ?? $decoded['p'] ?? false),
                        'target_roles' => $decoded['target_roles'] ?? $decoded['tr'] ?? [],
                        'target_user_ids' => $decoded['target_user_ids'] ?? $decoded['tu'] ?? [],
                    ];
                }

                return [
                        'notification_id' => $notification->id,
                        'title' => 'Notification',
                        'message' => (string) $notification->caption,
                        'type' => 'info',
                    ];
            })
            ->values();

        if ($notifications->isNotEmpty()) {
            Notification::whereIn('id', $notifications->pluck('id'))->delete();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        //
    }
}
