<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Enums\RecordType;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly UnitCatalog $units,
        private readonly StockLedger $ledger,
        private readonly TenantAuthorizer $authorizer,
    ) {}

    public function create(array $payload, int $userId): StockTransfer
    {
        $source = (int) $payload['source_branch_id'];
        $destination = (int) $payload['destination_branch_id'];
        $this->assertBranches($source, $destination, $userId);

        return DB::transaction(function () use ($payload, $userId, $source, $destination): StockTransfer {
            $rows = collect($payload['lines']);
            $itemIds = $rows->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $items = Item::where('tenant_id', $this->context->tenantId())->whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages(['lines' => 'One or more items do not belong to this account.']);
            }

            $normalised = [];
            foreach ($rows as $index => $row) {
                $item = $items->get((int) $row['item_id']);
                $unit = $this->units->find((string) ($row['unit_code'] ?? $item->default_unit_code));
                if (! $unit || $unit->family !== $item->family) {
                    throw ValidationException::withMessages(["lines.{$index}.unit_code" => 'Choose a unit from the item measurement.']);
                }
                $normalised[] = ['item' => $item, 'qty' => (float) $row['qty'], 'unit' => $unit, 'base_qty' => (float) $row['qty'] * $unit->base_multiplier];
            }

            $this->assertAvailable($normalised, $source);
            $sourcePositions = $this->ledger->positionsForBranch($items, $source);

            foreach ($normalised as $index => $line) {
                $totalCost = $sourcePositions->get($line['item']->id)?->costOf($line['base_qty']);
                $normalised[$index]['total_cost'] = $totalCost;
                $normalised[$index]['unit_cost'] = $totalCost !== null && $line['qty'] > 0
                    ? round($totalCost / $line['qty'], 6)
                    : null;
            }

            $transfer = StockTransfer::create([
                'tenant_id' => $this->context->tenantId(),
                'source_branch_id' => $source,
                'destination_branch_id' => $destination,
                'user_id' => $userId,
                'transferred_on' => $payload['transferred_on'],
                'status' => 'completed',
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($normalised as $line) {
                $transfer->lines()->create([
                    'item_id' => $line['item']->id,
                    'qty' => $line['qty'],
                    'unit_code' => $line['unit']->code,
                    'base_qty' => $line['base_qty'],
                    'unit_cost' => $line['unit_cost'],
                    'total_cost' => $line['total_cost'],
                ]);
            }

            $purchase = Transaction::create([
                'tenant_id' => $this->context->tenantId(),
                'branch_id' => $destination,
                'type' => RecordType::Purchase,
                'transacted_on' => $payload['transferred_on'],
                'user_id' => $userId,
                'stock_transfer_id' => $transfer->id,
            ]);

            foreach ($normalised as $line) {
                TransactionLine::create([
                    'transaction_id' => $purchase->id,
                    'item_id' => $line['item']->id,
                    'qty' => $line['qty'],
                    'unit_code' => $line['unit']->code,
                    'base_qty' => $line['base_qty'],
                    'unit_price' => $line['unit_cost'] ?? 0,
                    'total' => $line['total_cost'] ?? 0,
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            return $transfer->load(['lines.item', 'lines.unit', 'sourceBranch', 'destinationBranch', 'purchase.lines']);
        });
    }

    private function assertBranches(int $source, int $destination, int $userId): void
    {
        if ($source === $destination || $source < 1 || $destination < 1) {
            throw ValidationException::withMessages(['destination_branch_id' => 'Choose two different branches.']);
        }

        $count = $this->context->tenant()->branches()->whereIn('id', [$source, $destination])->where('is_active', true)->count();
        if ($count !== 2) {
            throw ValidationException::withMessages(['source_branch_id' => 'Both branches must belong to this account and be active.']);
        }

        $user = User::find($userId);
        if ($user instanceof User) {
            $allowed = $this->authorizer->allowedBranchIds($user, $this->context->tenant());

            if (! in_array($source, $allowed, true) || ! in_array($destination, $allowed, true)) {
                throw ValidationException::withMessages(['source_branch_id' => 'You can only transfer between branches you can access.']);
            }
        }
    }

    private function assertAvailable(array $lines, int $source): void
    {
        if ($this->context->tenant()->stock_management_mode->value === 'account') {
            return;
        }

        $positions = $this->ledger->positionsForBranch(
            Item::where('tenant_id', $this->context->tenantId())
                ->whereIn('id', collect($lines)->map(fn ($line) => $line['item']->id)->all())
                ->get(),
            $source,
        );
        foreach ($lines as $line) {
            $available = $positions->get($line['item']->id)?->stock() ?? 0;
            if ($line['base_qty'] > $available + $this->ledger->tolerance()) {
                throw ValidationException::withMessages(['lines' => "Not enough stock for {$line['item']->name}."]);
            }
        }
    }
}
