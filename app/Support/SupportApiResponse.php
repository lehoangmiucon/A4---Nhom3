<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SupportApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => ['request_id' => (string) Str::uuid()] + $meta], $status)
            ->header('Cache-Control', 'no-store');
    }

    public static function error(Throwable $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof ValidationException => 422,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        [$code, $message] = match ($status) {
            401 => ['UNAUTHENTICATED', 'Vui lòng đăng nhập.'],
            403 => ['FORBIDDEN', 'Tài khoản không được phép sử dụng chức năng này.'],
            404 => ['NOT_FOUND', 'Không tìm thấy dữ liệu yêu cầu.'],
            409 => ['STATE_CONFLICT', 'Phiên đã thay đổi hoặc đang xử lý. Hãy tải lại hội thoại trước khi tiếp tục.'],
            422 => ['VALIDATION_ERROR', 'Dữ liệu chưa hợp lệ.'],
            429 => ['RATE_LIMITED', 'Vui lòng chờ trước khi thử lại.'],
            default => ['SERVICE_UNAVAILABLE', 'Chưa thể xử lý yêu cầu. Vui lòng thử lại.'],
        };
        $details = $exception instanceof ValidationException ? $exception->errors() : null;
        if (($details['idempotency_key'][0] ?? null) === 'IDEMPOTENCY_KEY_REUSED') {
            $code = 'IDEMPOTENCY_KEY_REUSED';
            $message = 'Khóa yêu cầu đã được dùng cho nội dung khác. Hãy dùng khóa mới cho yêu cầu mới.';
            $details = null;
        }
        $response = response()->json([
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'meta' => ['request_id' => (string) Str::uuid()],
        ], $status)->header('Cache-Control', 'no-store');
        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }
}
