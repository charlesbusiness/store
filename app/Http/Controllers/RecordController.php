<?php

namespace App\Http\Controllers;

use App\Enums\RecordType;
use App\Http\Requests\RecordTransactionRequest;
use App\Services\Record\PurchaseRecorder;
use App\Services\Record\RecordFormData;
use App\Services\Record\RecordWriter;
use App\Services\Record\SaleRecorder;
use App\Support\Concerns\FormatsValues;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Shared behaviour of the sell and buy tabs.
 *
 * Both render the same view and post the same payload; they differ only in
 * which side of the ledger they write to, so the subclass is a single method.
 */
abstract class RecordController extends Controller
{
    use FormatsValues;

    public function __construct(
        protected readonly RecordFormData $formData,
        protected readonly SaleRecorder $sales,
        protected readonly PurchaseRecorder $purchases,
    ) {}

    abstract protected function type(): RecordType;

    public function create(): View
    {
        return view('records.create', [
            'form' => $this->formData->build($this->type()),
        ]);
    }

    public function store(RecordTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->recorder()->record($request->payload(), $request->user());

        return redirect()
            ->route($this->type()->routeName().'create')
            ->with('status', sprintf(
                '%s of %s recorded for %s.',
                $this->type()->label(),
                $this->money($transaction->total()),
                $transaction->transacted_on->toFormattedDateString()
            ));
    }

    protected function recorder(): RecordWriter
    {
        return $this->type() === RecordType::Sale ? $this->sales : $this->purchases;
    }
}
