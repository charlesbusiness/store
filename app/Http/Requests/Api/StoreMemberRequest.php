<?php

namespace App\Http\Requests\Api;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'member'])],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('branches', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
        ];
    }
}
