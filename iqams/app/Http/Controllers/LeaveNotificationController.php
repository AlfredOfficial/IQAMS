<?php

namespace App\Http\Controllers;

use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveNotificationController extends Controller
{
    public function read(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()
            ->where('type', LeaveRequestNotification::class)
            ->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }
}
