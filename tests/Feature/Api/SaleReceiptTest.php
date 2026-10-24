<?php

namespace Tests\Feature\Api;

use App\Models\Transaction;
use Tests\TestCase;

class SaleReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function sale(string $date = '2026-10-24'): Transaction
    {
        $item = $this->item('Yam');
        $line = $this->recordLine($item, 'sale', 2, 'bag', 5000, $date);

        return $line->transaction()->firstOrFail();
    }

    public function test_a_sale_receipt_downloads_as_a_pdf(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->get("/api/sales/{$sale->id}/receipt");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload("receipt-sale-{$sale->id}.pdf");
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_receipt_shows_the_customer_facing_fields_only(): void
    {
        $sale = $this->sale()->load(['lines.item', 'lines.unit']);

        $html = view('receipts.sale', ['sale' => $sale])->render();

        $this->assertStringContainsString('Sales Receipt', $html);
        $this->assertStringContainsString('Yam', $html);
        $this->assertStringContainsString('Oct 24, 2026', $html);
        $this->assertStringContainsString('2', $html);
        $this->assertStringContainsString('5,000', $html);
        $this->assertStringContainsString('10,000', $html);
        $this->assertStringContainsString('Total Sales', $html);

        $this->assertStringNotContainsString('<td class="label">Receipt</td>', $html);
        $this->assertStringNotContainsString('#'.$sale->id.'</td>', $html);
        $this->assertStringNotContainsString('Cost', $html);
        $this->assertStringNotContainsString('Profit', $html);
    }

    public function test_the_receipt_includes_the_customer_name_when_supplied(): void
    {
        $sale = $this->sale()->load(['lines.item', 'lines.unit']);

        $html = view('receipts.sale', [
            'sale' => $sale,
            'customerName' => 'Ada Obi',
        ])->render();

        $this->assertStringContainsString('<td class="label">Customer</td>', $html);
        $this->assertStringContainsString('Ada Obi', $html);
    }

    public function test_the_receipt_omits_the_customer_row_when_no_name_is_supplied(): void
    {
        $sale = $this->sale()->load(['lines.item', 'lines.unit']);

        $html = view('receipts.sale', [
            'sale' => $sale,
            'customerName' => '',
        ])->render();

        $this->assertStringNotContainsString('<td class="label">Customer</td>', $html);
    }

    public function test_a_customer_name_can_be_passed_to_the_download(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->get("/api/sales/{$sale->id}/receipt?customer_name=Ada%20Obi");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload("sales-Ada Obi.pdf");
    }

    public function test_a_customer_name_is_limited_to_a_sensible_length(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $this->getJson("/api/sales/{$sale->id}/receipt?customer_name=".str_repeat('a', 121))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_a_receipt_requires_authentication(): void
    {
        $sale = $this->sale();

        $this->get("/api/sales/{$sale->id}/receipt")->assertRedirect('/login');
    }

    public function test_a_purchase_cannot_be_downloaded_as_a_sale_receipt(): void
    {
        $this->actingAs($this->user());

        $item = $this->item('Yam');
        $line = $this->recordLine($item, 'purchase', 2, 'bag', 5000, '2026-10-24');
        $purchase = $line->transaction()->firstOrFail();

        $this->get("/api/sales/{$purchase->id}/receipt")->assertNotFound();
    }

    public function test_an_authenticated_user_can_generate_a_receipt_share_link(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->getJson("/api/sales/{$sale->id}/receipt-link");

        $response->assertOk();
        $response->assertJsonStructure(['url']);
        $this->assertStringContainsString('/receipts/sales/'.$sale->id, $response->json('url'));
        $this->assertStringContainsString('signature=', $response->json('url'));
    }

    public function test_a_receipt_share_link_can_include_a_customer_name(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->getJson("/api/sales/{$sale->id}/receipt-link?customer_name=Ada%20Obi");

        $response->assertOk();
        $url = $response->json('url');
        $this->assertStringContainsString('customer_name=', $url);
    }

    public function test_a_public_signed_receipt_link_opens_the_pdf(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->getJson("/api/sales/{$sale->id}/receipt-link");
        $url = $response->json('url');

        $this->app['auth']->forgetGuards();

        $public = $this->get($url);
        $public->assertOk();
        $public->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $public->getContent());
    }

    public function test_a_tampered_receipt_signature_is_rejected(): void
    {
        $this->actingAs($this->user());

        $sale = $this->sale();

        $response = $this->getJson("/api/sales/{$sale->id}/receipt-link");
        $url = $response->json('url');

        $this->app['auth']->forgetGuards();

        $this->get($url.'&signature=invalid')->assertStatus(403);
    }

    public function test_a_missing_receipt_signature_is_rejected(): void
    {
        $sale = $this->sale();

        $this->get("/receipts/sales/{$sale->id}")->assertStatus(403);
    }

    public function test_a_purchase_cannot_generate_a_sale_receipt_link(): void
    {
        $this->actingAs($this->user());

        $item = $this->item('Yam');
        $line = $this->recordLine($item, 'purchase', 2, 'bag', 5000, '2026-10-24');
        $purchase = $line->transaction()->firstOrFail();

        $this->getJson("/api/sales/{$purchase->id}/receipt-link")->assertNotFound();
    }
}
