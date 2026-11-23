<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);
        PushDevice::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            ['user_id' => $request->user()->id, 'token' => $data['token'], 'platform' => $data['platform']],
        );

        return response()->json(['message' => 'Đã đăng ký thiết bị nhận thông báo.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        PushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();

        return response()->json(['message' => 'Đã hủy đăng ký thiết bị.']);
    }
}
