<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPush
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $credentials = $this->credentials();
        if (!$credentials) return;

        try {
            $accessToken = $this->accessToken($credentials);
            $projectId = config('services.fcm.project_id') ?: $credentials['project_id'];
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $projectId)) return;
            $unreadCount = User::query()->find($userId)?->unreadNotifications()->count() ?? 0;
            foreach (PushDevice::query()->where('user_id', $userId)->get() as $device) {
                $response = Http::withToken($accessToken)->timeout(12)
                    ->retry(3, 500, fn (\Throwable $error): bool => $error instanceof ConnectionException
                        || ($error instanceof RequestException && $error->response->serverError()))
                    ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    ['message' => [
                        'token' => $device->token,
                        'notification' => ['title' => $title, 'body' => mb_substr($body, 0, 200)],
                        'data' => array_map('strval', $data),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'helpdesk_alerts_v2',
                                'sound' => 'default',
                                'notification_count' => $unreadCount,
                            ],
                        ],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default', 'badge' => $unreadCount]]],
                    ]],
                );
                if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    $device->delete();
                } elseif ($response->failed()) {
                    Log::warning('FCM send failed', ['status' => $response->status()]);
                }
            }
        } catch (\Throwable $error) {
            Log::warning('FCM push unavailable', ['error' => $error->getMessage()]);
        }
    }

    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (!$path || !is_file($path)) return null;
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) && isset($data['client_email'], $data['private_key'], $data['project_id']) ? $data : null;
    }

    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_'.hash('sha256', $credentials['client_email']), 3000, function () use ($credentials): string {
            $now = time();
            $encode = fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);
            if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Cannot sign FCM request.');
            }
            $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $response = Http::asForm()->timeout(12)
                ->retry(3, 500, fn (\Throwable $error): bool => $error instanceof ConnectionException
                    || ($error instanceof RequestException && $error->response->serverError()))
                ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw();
            return $response->json('access_token');
        });
    }
}
