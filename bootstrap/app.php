<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureAccountActive;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prependToGroup('api', \App\Http\Middleware\SwaggerTokenRequests::class);
        $middleware->alias([
            'role' => EnsureRole::class,
            'account.active' => EnsureAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $expectsJson = fn (Request $request): bool =>
            $request->is('api/*') || $request->expectsJson() || $request->ajax();

        $exceptions->shouldRenderJsonWhen($expectsJson);

        $exceptions->render(function (Throwable $exception, Request $request) use ($expectsJson) {
            $json = $expectsJson($request);

            if ($exception instanceof ValidationException && ! $json) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof ThrottleRequestsException => 429,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $errors = $exception instanceof ValidationException ? $exception->errors() : [];
            $validationSummary = $exception instanceof ValidationException ? $exception->getMessage() : null;

            $message = match (true) {
                $exception instanceof ValidationException =>
                    is_string($validationSummary) && $validationSummary !== ''
                        ? $validationSummary
                        : 'Dữ liệu gửi lên chưa hợp lệ. Vui lòng kiểm tra lại.',
                $exception instanceof AuthenticationException =>
                    'Vui lòng đăng nhập để tiếp tục.',
                $exception instanceof AuthorizationException =>
                    'Bạn không có quyền thực hiện thao tác này.',
                $exception instanceof ModelNotFoundException =>
                    'Không tìm thấy dữ liệu yêu cầu.',
                $exception instanceof TokenMismatchException =>
                    'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.',
                $exception instanceof ThrottleRequestsException =>
                    'Bạn thao tác quá nhanh. Vui lòng chờ một lúc rồi thử lại.',
                $status === 403 =>
                    filled($exception->getMessage()) && ! in_array(strtolower($exception->getMessage()), ['forbidden', 'this action is unauthorized.'], true)
                        ? $exception->getMessage()
                        : 'Bạn không có quyền thực hiện thao tác này.',
                $status === 404 =>
                    filled($exception->getMessage()) && strtolower($exception->getMessage()) !== 'not found'
                        ? $exception->getMessage()
                        : 'Không tìm thấy trang hoặc dữ liệu yêu cầu.',
                $status === 419 =>
                    'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.',
                $status === 429 =>
                    'Bạn thao tác quá nhanh. Vui lòng chờ một lúc rồi thử lại.',
                $status >= 500 =>
                    'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.',
                $exception instanceof HttpExceptionInterface && filled($exception->getMessage()) =>
                    $exception->getMessage(),
                default =>
                    'Đã xảy ra lỗi. Vui lòng thử lại.',
            };

            if ($json) {
                return response()->json([
                    'message' => $message,
                    ...($errors !== [] ? ['errors' => $errors] : []),
                ], $status);
            }

            return response()->view('errors.localized', [
                'message' => $message,
                'status' => $status,
            ], $status);
        });
    })->create();
