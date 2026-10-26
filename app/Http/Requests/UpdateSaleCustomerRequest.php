<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a correction to the optional customer named on a sale.
 */
class UpdateSaleCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'customer name',
        ];
    }

    public function customerName(): ?string
    {
        $name = trim((string) ($this->validated('customer_name') ?? ''));

        return $name === '' ? null : $name;
    }
}
