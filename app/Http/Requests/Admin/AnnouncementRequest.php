<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Enums\Classification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'array'],
            'audience.account_types' => ['required', 'array', 'min:1'],
            'audience.account_types.*' => [Rule::enum(AccountType::class)],
            'audience.classifications' => ['present', 'array'],
            'audience.classifications.*' => [Rule::enum(Classification::class)],
            'audience.barangays' => ['present', 'array'],
            'audience.barangays.*' => ['integer', 'exists:barangays,id'],
            'channels' => ['present', 'array'],
            'channels.*' => [Rule::in(['email', 'sms'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audience.account_types.required' => 'Choose at least one group of recipients.',
            'audience.account_types.min' => 'Choose at least one group of recipients.',
        ];
    }
}
