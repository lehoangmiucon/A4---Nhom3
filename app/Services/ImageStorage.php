<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImageStorage
{
    public static function avatar(UploadedFile $image, User $user): string
    {
        return self::store($image, 'avatars', 'USER-'.$user->getKey(), 'AVATAR', 'avatar');
    }

    public static function attachment(UploadedFile $image, Ticket $ticket, string $type): string
    {
        $directory = match ($type) {
            'report' => 'ticket-attachments/reports',
            'repair' => 'ticket-attachments/repairs',
        };

        return self::store($image, $directory, $ticket->ticket_number, strtoupper($type), 'attachments');
    }

    private static function store(UploadedFile $image, string $directory, string $code, string $type, string $field): string
    {
        $code = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $code), '-_') ?: 'IMAGE';
        $extension = $image->extension();
        if (! $extension) {
            throw ValidationException::withMessages([$field => 'Không xác định được định dạng ảnh. Vui lòng chọn ảnh khác.']);
        }

        $disk = Storage::disk('public');
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $timestamp = now('Asia/Ho_Chi_Minh')->format('Ymd_His');
            $filename = $code.'_'.$type.'_'.$timestamp.'_'.random_int(1000, 9999).'.'.strtolower($extension);
            if ($disk->exists($directory.'/'.$filename)) continue;

            $path = $image->storeAs($directory, $filename, 'public');
            if (! $path) {
                throw ValidationException::withMessages([$field => 'Không thể lưu ảnh. Vui lòng thử lại.']);
            }

            return $path;
        }

        throw ValidationException::withMessages([$field => 'Chưa thể tạo tên ảnh mới. Vui lòng thử lại.']);
    }
}
