<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin, UserRole::PetugasPiket) ?? false;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;
        $terminal = $this->route('terminal');

        return [
            'terminal_code' => [
                'required', 'string', 'max:50',
                Rule::unique('terminals', 'terminal_code')
                    ->where('school_id', $schoolId)
                    ->ignore($terminal?->id),
            ],
            'type' => ['required', Rule::in(['in', 'out'])],
            'location' => ['nullable', 'string', 'max:150'],
            'reader_profile_id' => ['nullable', Rule::exists('reader_profiles', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
