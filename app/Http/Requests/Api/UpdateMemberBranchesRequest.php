<?php

namespace App\Http\Requests\Api;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
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
