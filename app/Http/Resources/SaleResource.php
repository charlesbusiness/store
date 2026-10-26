<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customerName?->name,
            'transacted_on' => $this->transacted_on?->toDateString(),
            'total' => (float) $this->total,
            'cost' => $this->cost ? (float) $this->cost : null,
            'profit' => $this->profit !== null ? (float) $this->profit : null,
            'paid' => (bool) $this->paid,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_name' => $line->item?->name,
                'qty' => (float) $line->qty,
                'quantity' => $line->unit?->quantityLabel($line->qty) ?? (string) $line->qty,
                'unit_code' => $line->unit_code,
                'unit_label' => $line->unit?->label,
                'base_qty' => (float) $line->base_qty,
                'unit_price' => (float) $line->unit_price,
                'total' => (float) $line->total,
                'unit_cost' => $line->unit_cost ? (float) $line->unit_cost : null,
                'selling_price' => $line->selling_price ? (float) $line->selling_price : null,
                'cost' => $line->cost ? (float) $line->cost : null,
                'profit' => $line->profit() !== null ? (float) $line->profit() : null,
                'inner_units' => $line->inner_units ?? [],
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
