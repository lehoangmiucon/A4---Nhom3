<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->latest()->paginate(20);
        $page->getCollection()->transform(fn($notification) => [
            'id' => $notification->id,
            'ticket_id' => $notification->data['ticket_id'] ?? null,
            'title' => $notification->data['title'] ?? 'Cập nhật Helpdesk',
            'body' => $notification->data['message'] ?? '',
            'status' => $notification->data['status'] ?? null,
            'created_at' => $notification->created_at,
            'read_at' => $notification->read_at,
        ]);
        return response()->json($page);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();
        return response()->json(['message' => 'Đã đánh dấu thông báo đã đọc.']);
    }

    public function readTicket(Request $request, int $ticketId): JsonResponse
    {
        $updated = $request->user()->notifications()
            ->where('data->ticket_id', $ticketId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Đã đánh dấu các cập nhật của phiếu là đã đọc.',
            'updated' => $updated,
        ]);
    }
}
