<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\ActivityLog;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function buildings(): JsonResponse
    {
        return response()->json(['data' => Building::with('rooms')->withCount('rooms')->orderBy('code')->get()]);
    }

    public function createBuilding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:buildings,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $building = Building::create($data);
        ActivityLog::record($request, 'building.created', $building, 'Created a building.', ['code' => $building->code]);
        return response()->json(['message' => 'Đã tạo tòa nhà.', 'data' => $building], 201);
    }

    public function updateBuilding(Request $request, Building $building): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('buildings', 'code')->ignore($building->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $building->update($data);
        ActivityLog::record($request, 'building.updated', $building, 'Updated a building.', ['changed_fields' => array_keys($data)]);
        return response()->json(['message' => 'Đã cập nhật tòa nhà.', 'data' => $building]);
    }

    public function deleteBuilding(Request $request, Building $building): JsonResponse
    {
        ActivityLog::record($request, 'building.deleted', $building, 'Deleted a building.', ['code' => $building->code]);
        $building->delete();
        return response()->json(['message' => 'Đã xóa tòa nhà và các phòng thuộc tòa nhà.']);
    }

    public function createRoom(Request $request, Building $building): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('rooms', 'code')->where('building_id', $building->id)],
            'name' => ['required', 'string', 'max:120'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        $room = $building->rooms()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'floor' => $data['floor'] ?? $this->floorFromRoomCode($data['code']),
            'qr_code' => $data['code'],
        ]);
        ActivityLog::record($request, 'room.created', $room, 'Created a room.', ['building_id' => $building->id, 'code' => $room->code]);
        return response()->json(['message' => 'Đã tạo phòng và mã QR.', 'data' => $room], 201);
    }

    public function updateRoom(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('rooms', 'code')->where('building_id', $room->building_id)->ignore($room->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        if (isset($data['code'])) $data['qr_code'] = $data['code'];
        $room->update($data);
        ActivityLog::record($request, 'room.updated', $room, 'Updated a room.', ['changed_fields' => array_keys($data)]);
        return response()->json(['message' => 'Đã cập nhật phòng.', 'data' => $room]);
    }

    private function floorFromRoomCode(string $code): ?int
    {
        if (!preg_match('/(\d{3,4})$/', trim($code), $matches)) {
            return null;
        }

        $floorDigits = substr($matches[1], 0, -2);

        return $floorDigits === '' ? null : (int) $floorDigits;
    }

    public function deleteRoom(Request $request, Room $room): JsonResponse
    {
        ActivityLog::record($request, 'room.deleted', $room, 'Deleted a room.', ['code' => $room->code]);
        $room->delete();
        return response()->json(['message' => 'Đã xóa phòng.']);
    }
}
