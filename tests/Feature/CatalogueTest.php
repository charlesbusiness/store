<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Unit;
use Tests\TestCase;

/**
 * The catalogue forms. The rule worth protecting: once an item or a unit has
 * been used to record a quantity, the fields that give it meaning are frozen,
 * so history cannot be silently restated.
 *
 * An item's family is not a field either. It is read off the unit the item is
 * traded in, so the two cannot be submitted in disagreement.
 */
class CatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
        $this->actingAs($this->user());
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rice',
            'default_unit_code' => 'bag',
            'pack_size' => '50kg',
            'notes' => null,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_an_item_is_created_with_its_family_read_off_its_unit(): void
    {
        $this->post(route('items.store'), $this->itemPayload())->assertSessionHasNoErrors();

        $item = Item::where('name', 'Rice')->firstOrFail();

        $this->assertSame('count', $item->family->value);
        $this->assertSame('rice', $item->name_key);
        $this->assertTrue($item->is_active);

        // Kilograms make a weight item, whatever family the payload claimed.
        $this->post(route('items.store'), $this->itemPayload([
            'name' => 'Sugar', 'default_unit_code' => 'kg', 'family' => 'count',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('weight', Item::where('name', 'Sugar')->firstOrFail()->family->value);
    }

    public function test_a_bad_or_duplicate_item_is_rejected(): void
    {
        // Needs a real, active unit: an unknown one is refused.
        $this->post(route('items.store'), $this->itemPayload(['default_unit_code' => 'nope']))
            ->assertSessionHasErrors('default_unit_code');

        // Compared on the normalised key, so case and padding cannot slip past.
        $this->item('Rice', 'count', 'bag');
        $this->post(route('items.store'), $this->itemPayload(['name' => '  RICE ']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Item::count());
    }

    public function test_an_item_that_has_traded_keeps_its_family_and_unit(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 5, 'bag', 25000);

        // The form disables these inputs, so they are absent rather than wrong.
        // Editing the rest of the item, and retiring it, both still work.
        $this->put(route('items.update', $item), [
            'name' => 'Rice (premium)',
            'pack_size' => '50kg',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('Rice (premium)', $item->name);
        $this->assertFalse($item->is_active);
        $this->assertSame('count', $item->family->value);
        $this->assertSame('bag', $item->default_unit_code);

        // Sent explicitly, the frozen unit is refused outright.
        $this->put(route('items.update', $item), [
            'name' => 'Rice',
            'default_unit_code' => 'kg',
        ])->assertSessionHasErrors('default_unit_code');
    }

    public function test_a_unit_can_be_created_and_edited_while_unused(): void
    {
        $this->post(route('units.store'), [
            'code' => 'handcart',
            'label' => 'Handcart',
            'family' => 'count',
            'base_multiplier' => '48',
            'is_decimal' => '1',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $unit = Unit::where('code', 'handcart')->firstOrFail();
        $this->assertSame(48.0, $unit->base_multiplier);

        // A malformed code and one already taken are both refused.
        foreach (['Handcart!', 'bag'] as $code) {
            $this->post(route('units.store'), [
                'code' => $code,
                'label' => 'Handcart',
                'family' => 'count',
                'base_multiplier' => '48',
            ])->assertSessionHasErrors('code');
        }

        // An unused unit keeps its code, family and multiplier editable.
        $this->put(route('units.update', $unit), [
            'code' => 'handcart',
            'label' => 'Handcart (large)',
            'family' => 'count',
            'base_multiplier' => '96',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Handcart (large)', $unit->refresh()->label);
        $this->assertSame(96.0, $unit->base_multiplier);
    }

    public function test_a_unit_that_has_been_used_keeps_its_code_family_and_multiplier(): void
    {
        $unit = $this->unit('bag');
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 2, 'bag', 20000);

        $this->assertTrue($unit->isInUse());

        // Only the label arrives, because the rest of the form is disabled.
        $this->put(route('units.update', $unit), [
            'label' => 'Sack',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertSame('Sack', $unit->label);
        $this->assertSame('bag', $unit->code);
        $this->assertSame('count', $unit->family->value);
        $this->assertSame(1.0, $unit->base_multiplier);

        $this->put(route('units.update', $unit), [
            'label' => 'Sack',
            'code' => 'sack',
            'family' => 'weight',
            'base_multiplier' => '2',
        ])->assertSessionHasErrors(['code', 'family', 'base_multiplier']);
    }
}
