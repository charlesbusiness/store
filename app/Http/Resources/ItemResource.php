<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'family' => $this->family?->value,
            'unit' => $this->unit?->code,
            'stock' => $this->stock ?? 0,
            'stockLabel' => $this->stockLabel ?? '',
            'costPerUnit' => $this->costPerUnit ?? 0,
            'lastSellingPrice' => $this->lastSellingPrice ?? 0,
            'packs' => $this->whenLoaded('packs', fn () => $this->packs->toArray()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
