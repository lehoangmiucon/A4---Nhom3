<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json(['data' => Category::query()->orderBy('name')->get(['id', 'name', 'color'])]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Category::withCount(['tickets', 'equipment'])->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $category = Category::create($data);
        ActivityLog::record($request, 'category.created', $category, 'Created an issue category.', ['name' => $category->name]);
        return response()->json(['message' => 'Đã tạo danh mục lỗi.', 'data' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $category->update($data);
        ActivityLog::record($request, 'category.updated', $category, 'Updated an issue category.', ['changed_fields' => array_keys($data)]);
        return response()->json(['message' => 'Đã cập nhật danh mục lỗi.', 'data' => $category->fresh()]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->tickets()->exists() || $category->equipment()->exists() || $category->staff()->exists()) {
            return response()->json(['message' => 'Danh mục đang được phiếu hoặc thiết bị sử dụng, không thể xóa.'], 422);
        }

        ActivityLog::record($request, 'category.deleted', $category, 'Deleted an issue category.', ['name' => $category->name]);
        $category->delete();
        return response()->json(['message' => 'Đã xóa danh mục lỗi.']);
    }
}
