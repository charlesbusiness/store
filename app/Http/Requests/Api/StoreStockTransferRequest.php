<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'source_branch_id' => ['required', 'integer', 'different:destination_branch_id'],
            'destination_branch_id' => ['required', 'integer', 'different:source_branch_id'],
            'transferred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
