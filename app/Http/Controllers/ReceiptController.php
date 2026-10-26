<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Customer receipts for sales.
 *
 * The PDF is deliberately customer-facing: cost and profit stay out of it.
 * A receipt can be downloaded directly, or shared through a temporary signed
 * URL that lets the customer open it without signing in.
 */
class ReceiptController extends Controller
{
    public function download(Transaction $sale): Response
    {
        $customerName = $this->customerName($sale);
        $filename = $customerName !== ''
            ? $this->safeReceiptFilename('sales-'.$customerName)
            : "receipt-sale-{$sale->id}";

        return $this->render($sale)
            ->download($filename.'.pdf');
    }

    private function safeReceiptFilename(string $name): string
    {
        $name = trim(preg_replace('/[^A-Za-z0-9 _.-]+/', ' ', $name) ?? '');
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return 'receipt';
        }

        return mb_substr($name, 0, 80);
    }

    public function show(Transaction $sale): Response
    {
        return $this->render($sale)
            ->stream("receipt-sale-{$sale->id}.pdf");
    }

    public function shareLink(Transaction $sale): JsonResponse
    {
        $this->ensureSale($sale);

        $expiresIn = max(1, (int) config('stockly.receipt_share_days', 30));

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'receipts.public',
                now()->addDays($expiresIn),
                ['sale' => $sale->id],
            ),
        ]);
    }

    private function render(Transaction $sale): DomPdf
    {
        $this->ensureSale($sale);

        $sale->load(['customerName', 'lines.item', 'lines.unit']);

        return Pdf::loadView('receipts.sale', [
            'sale' => $sale,
        ])->setPaper('a5');
    }

    private function customerName(Transaction $sale): string
    {
        return trim((string) ($sale->customerName?->name ?? ''));
    }

    private function ensureSale(Transaction $sale): void
    {
        abort_unless($sale->isSale(), 404);
    }
}
