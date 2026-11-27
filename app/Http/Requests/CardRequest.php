<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::PetugasPiket) ?? false;
    }

    public function rules(): array
    {
        $card = $this->route('card');

        return [
            'uid_text' => [
                $this->isMethod('POST') ? 'required' : 'nullable', 'string', 'max:64',
                Rule::unique('rfid_cards', 'uid_text')->ignore($card?->id),
            ],
            'card_type' => ['nullable', 'string', 'max:30'],
            'issued_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'unassigned', 'suspended', 'lost', 'damaged', 'replaced', 'revoked', 'archived'])],
        ];
    }
}
