<?php

namespace App\Services\Record;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Models\User;
use App\Services\Stock\AvailabilityChecker;
use App\Support\Service;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared machinery for recording a sale or a purchase.
 *
 * The sequence is fixed: resolve the rows into items, normalise the lines,
 * take a row lock on every item involved, re-check the rules that the browser
 * could not be trusted for, then write. Everything runs inside one
 * transaction, so a purchase that names a brand new item leaves no orphan
 * behind when a later line fails. Subclasses decide how items are resolved and
 * what extra guard applies.
 */
abstract class RecordWriter extends Service
{
    public function __construct(
        protected readonly LineNormalizer $normalizer,
        protected readonly AvailabilityChecker $availability,
    ) {}

    /** Which side of the ledger this writer records. */
    abstract public function type(): RecordType;

    /**
     * Turn the submitted rows into items, returning rows with item_id filled in.
     *
     * @param  array<int, array<string, mixed>>  $rows
     *
     * @throws ValidationException
     */
    abstract protected function resolve(array $rows): ResolvedLines;

    /**
     * Record a sale or purchase and return the stored transaction.
     *
     * @param  array{transacted_on: string, lines: array<int, array<string, mixed>>}  $payload
     *
     * @throws ValidationException
     */
    public function record(array $payload, ?User $user = null): Transaction
    {
        $rows = array_values($payload['lines'] ?? []);

        // The form request checks this too, but the service is reachable
        // directly, and a transaction with no lines is not a record.
        if ($rows === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line.']);
        }

        return DB::transaction(function () use ($payload, $rows, $user) {
            $resolved = $this->resolve($rows);
            $lines = $this->normalizer->normalise($this->type(), $resolved->rows, $resolved->items);

            $this->lockItems($lines);

            // Re-checked under the lock: the form's own check may be minutes old.
            $this->guard($lines);

            $transaction = Transaction::create([
                'type' => $this->type(),
                'transacted_on' => $payload['transacted_on'],
                'user_id' => $user?->id,
            ]);

            foreach ($lines as $line) {
                TransactionLine::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $line['item']->id,
                    'qty' => $line['qty'],
                    'unit_code' => $line['unit']->code,
                    'base_qty' => $line['base_qty'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['total'],
                    'inner_units' => $line['inner_units'] ?: null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'cost' => $this->costFor($line),
                ]);
            }

            return $transaction->load('lines');
        });
    }

    /**
     * What this line's stock cost, frozen at the moment it is written.
     *
     * Null by default, and that is the right answer for a purchase: the total on
     * the line is already what was paid, so a second figure beside it would be
     * the same number under a different name. Only a sale has a cost that is not
     * already on the line, because what it cost then is not what it sells for.
     *
     * @param  array{item: Item, unit: Unit, qty: float, base_qty: float, unit_price: float, total: float, inner_units?: array|null, unit_cost?: float|null}  $line
     */
    protected function costFor(array $line): ?float
    {
        return null;
    }

    /**
     * Lock every item on the form, in a stable order so two concurrent saves
     * cannot deadlock each other.
     */
    protected function lockItems(BaseCollection $lines): void
    {
        $ids = $lines->pluck('item.id')->unique()->sort()->values();

        if ($ids->isNotEmpty()) {
            Item::whereIn('id', $ids)->lockForUpdate()->get();
        }
    }

    /**
     * Rules that must hold at write time. Purchases have none, since they only
     * ever add stock.
     */
    protected function guard(BaseCollection $lines): void
    {
        //
    }
}
