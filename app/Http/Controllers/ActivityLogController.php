<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('user:id,name,email,role')->latest('id');
        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.mb_substr($request->string('action')->toString(), 0, 80).'%');
        }
        if ($request->filled('user_id')) $query->where('user_id', $request->integer('user_id'));

        return response()->json($query->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }
}
