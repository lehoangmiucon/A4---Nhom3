<?php

namespace App\Http\Requests\Support;

use App\Models\SupportSession;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupportSession::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'min:1'],
            'equipment_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:128', 'regex:/\A[a-zA-Z0-9._:-]+\z/'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'version' => ['prohibited'],
            'messages' => ['prohibited'],
        ];
    }
}
