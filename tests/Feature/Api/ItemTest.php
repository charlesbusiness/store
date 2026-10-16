<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Unit;
use App\Enums\UnitFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user());
    }

    public function test_user_can_list_items(): void
    {
        // Create units for all families
        foreach (UnitFamily::cases() as $family) {
            Unit::factory()->create([
                'code' => 'pcs-' . $family->value,
                'family' => $family,
            ]);
        }
        
        Item::factory()->count(3)->create();

        $response = $this->getJson('/api/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'family', 'unit', 'stock', 'stockLabel'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_user_can_create_item(): void
    {
        Unit::factory()->create([
            'code' => 'pcs-count',
            'family' => UnitFamily::Count,
        ]);

        $response = $this->postJson('/api/items', [
            'name' => 'Test Item',
            'family' => UnitFamily::Count->value,
            'default_unit_code' => 'pcs-count',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'family', 'unit'],
            ]);
    }

    public function test_user_can_get_item(): void
    {
        foreach (UnitFamily::cases() as $family) {
            Unit::factory()->create([
                'code' => 'pcs-' . $family->value,
                'family' => $family,
            ]);
        }
        
        $item = Item::factory()->create();

        $response = $this->getJson("/api/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'family', 'unit', 'stock', 'stockLabel', 'costPerUnit', 'lastSellingPrice', 'packs'],
            ]);
    }

    public function test_user_can_update_item(): void
    {
        foreach (UnitFamily::cases() as $family) {
            Unit::factory()->create([
                'code' => 'pcs-' . $family->value,
                'family' => $family,
            ]);
        }
        
        $item = Item::factory()->create();

        $response = $this->putJson("/api/items/{$item->id}", [
            'name' => 'Updated Item Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Item Name');
    }

    public function test_user_can_delete_item(): void
    {
        foreach (UnitFamily::cases() as $family) {
            Unit::factory()->create([
                'code' => 'pcs-' . $family->value,
                'family' => $family,
            ]);
        }
        
        $item = Item::factory()->create();

        $response = $this->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Item deleted']);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_item_validation_requires_name(): void
    {
        Unit::factory()->create([
            'code' => 'pcs-count',
            'family' => UnitFamily::Count,
        ]);
        
        $response = $this->postJson('/api/items', [
            'family' => UnitFamily::Count->value,
            'default_unit_code' => 'pcs-count',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

public function test_item_validation_requires_unique_name(): void
    {
        foreach (UnitFamily::cases() as $family) {
            Unit::factory()->create([
                'code' => 'pcs-' . $family->value,
                'family' => $family,
            ]);
        }
        
        Item::factory()->create(['name' => 'Existing Item']);

        $response = $this->postJson('/api/items', [
            'name' => 'Existing Item',
            'family' => UnitFamily::Count->value,
            'default_unit_code' => 'pcs-count',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
