<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'stock_management_mode' => ['sometimes', Rule::in(['account', 'branch'])],
            'reporting_mode' => ['sometimes', Rule::in(['account', 'branch'])],
        ];
    }
}
