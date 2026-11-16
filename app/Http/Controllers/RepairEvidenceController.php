<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RepairEvidenceController extends Controller
{
    public function show(Request $request, Ticket $ticket, TicketAttachment $attachment): BinaryFileResponse
    {
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id && $attachment->attachment_type === 'repair', 404);
        $user = $request->user();
        abort_unless($user->role === 'admin' || ($user->role === 'staff' && (int) $ticket->assigned_to_id === (int) $user->id), 403);

        $disk = Storage::disk('repair-evidence');
        abort_unless($disk->exists($attachment->path), 404);

        return response()->file($disk->path($attachment->path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }
}
