<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'symbol' => $this->symbol,
            'plural' => $this->plural,
            'family' => $this->family?->value,
            'base_multiplier' => (float) $this->base_multiplier,
            'is_fraction' => $this->is_fraction,
            'is_decimal' => $this->is_decimal,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
