<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page renders for a signed-in user and none are reachable without one.
 * Guards the whole view layer against a missing variable or bad enum access.
 */
class PageRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    public static function pages(): array
    {
        return [
            'reports' => ['/reports'],
            'sell' => ['/sales'],
            'buy' => ['/purchases'],
            'stock' => ['/stock'],
            'items' => ['/items'],
            'units' => ['/units'],
        ];
    }

    #[DataProvider('pages')]
    public function test_guests_are_sent_to_the_login_page(string $path): void
    {
        $this->get($path)->assertRedirect(route('login'));
    }

    #[DataProvider('pages')]
    public function test_the_page_renders_for_a_signed_in_user(string $path): void
    {
        $this->actingAs($this->user())->get($path)->assertOk();
    }

    public function test_every_form_renders(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        $this->actingAs($this->user());

        $this->get(route('items.create'))->assertOk();
        $this->get(route('items.edit', $item))->assertOk();
        $this->get(route('units.create'))->assertOk();
        $this->get(route('units.edit', $this->unit('bag')))->assertOk();

        // Every grain, and the root that stands in for the default one.
        foreach (['d', 'w', 'm'] as $mode) {
            $this->get(route('reports.index', ['mode' => $mode, 'anchor' => '2026-10-05']))->assertOk();
        }

        $this->get('/')->assertRedirect('reports');

        // An unknown grain and an unreadable anchor both fall back rather than error.
        $this->get(route('reports.index', ['mode' => 'hourly', 'anchor' => 'not-a-date']))->assertOk();
    }

    public function test_a_negative_position_is_shown_as_out(): void
    {
        $item = $this->item('Cement', 'weight', 'sack_50kg');
        $this->recordLine($item, 'purchase', 1, 'sack_50kg', 13200);
        $this->recordLine($item, 'sale', 2, 'sack_50kg', 12000);

        $this->actingAs($this->user())
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('OUT')
            ->assertSee('Cement');
    }

    public function test_an_item_that_has_never_traded_is_still_listed(): void
    {
        $this->item('Never traded');

        $this->actingAs($this->user())
            ->get(route('items.index'))
            ->assertOk()
            ->assertSee('Never traded');
    }

    /**
     * Regression: the record forms ask for each item's last sale price, and those
     * items arrive as a plain collection rather than an Eloquent one. With an empty
     * catalogue nothing was ever iterated, so the mistake stayed hidden until
     * there was real stock to sell.
     */
    public function test_the_record_forms_render_with_items_that_have_traded(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $this->recordLine($rice, 'purchase', 5, 'bag', 25000);
        $this->recordLine($rice, 'sale', 2, 'bag', 11000);

        // Sold out, so the sell form has to cope with it being filtered away.
        $cement = $this->item('Cement', 'weight', 'sack_50kg');
        $this->recordLine($cement, 'purchase', 1, 'sack_50kg', 13200);
        $this->recordLine($cement, 'sale', 1, 'sack_50kg', 14000);

        $this->actingAs($this->user());

        $this->get(route('sales.create'))
            ->assertOk()
            ->assertSee('Rice')
            // The last price is handed to the form as JSON to pre-fill the box.
            ->assertSee('lastPrice":11000', false);

        $this->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Rice');
    }

    public function test_the_record_forms_offer_a_measurement_before_a_unit(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $this->recordLine($rice, 'purchase', 5, 'bag', 25000);

        $this->actingAs($this->user());

        foreach ([route('sales.create'), route('purchases.create')] as $path) {
            $response = $this->get($path)->assertOk()->assertSee('x-model="measurement"', false);

            // The picker iterates this, so it has to arrive as a list of value/label
            // pairs. A keyed object reads as empty here: x-for over an object has
            // nothing to bind, and every option comes out blank.
            $families = $this->recordFormConfig($response->getContent())['families'];

            $this->assertSame(['count', 'weight', 'volume', 'length', 'area'], array_column($families, 'value'));
            $this->assertSame('Pieces & containers', $families[0]['label']);
            $this->assertSame('Weight', $families[1]['label']);
        }
    }

    public function test_the_cart_preview_sits_outside_the_invoice_table(): void
    {
        $partial = file_get_contents(resource_path('views/records/partials/cart.blade.php'));

        $this->assertStringContainsString('class="invoice-preview"', $partial);
        $this->assertLessThan(
            strpos($partial, '<table>'),
            strpos($partial, 'class="invoice-preview"'),
            'the draft preview belongs above the invoice table',
        );
        $this->assertStringNotContainsString('<tr class="draft">', $partial);
        $this->assertStringContainsString('<td colspan="4">Sale total</td>', $partial);
        $this->assertStringContainsString('<td colspan="3">Purchase total</td>', $partial);
        $this->assertStringContainsString('x-show="lines.length > 0"', $partial);
    }

    public function test_the_item_form_picks_a_measurement_then_a_unit(): void
    {
        $this->actingAs($this->user())
            ->get(route('items.create'))
            ->assertOk()
            ->assertSee('data-unit-picker', false)
            ->assertSee('data-unit-group', false)
            ->assertSee('data-unit-value', false)
            // The optgroups carry their family, so the browser can filter on it and
            // the box still works with no JavaScript at all.
            ->assertSee('data-unit-group-body="count"', false)
            ->assertSee('data-unit-group-body="weight"', false);
    }

    public function test_the_destroy_confirmation_is_a_dialog_not_a_native_alert(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $this->recordLine($rice, 'sale', 1, 'bag', 11000);

        $this->actingAs($this->user())
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('id="stockly-confirm"', false)
            ->assertSee('Stockly.confirmDanger', false)
            // The wording rides in as an attribute, so an item named O'Reilly
            // cannot end the handler's JS string early.
            ->assertSee('data-confirm-text=', false)
            ->assertSee('confirmDanger(event)', false);

        // The native confirm is gone for good, not merely unused -- including as a
        // fallback, which is where it would otherwise have quietly survived.
        $script = file_get_contents(public_path('js/stockly.js'));

        $this->assertStringNotContainsString('window.confirm', $script);
        $this->assertStringNotContainsString('confirmSubmit', $script);
        $this->assertStringContainsString('showModal', $script);
    }
}
