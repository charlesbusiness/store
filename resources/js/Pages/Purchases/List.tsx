import { FormEvent, Fragment, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/Layout';
import { RecordModal, PurchaseForm } from '@/components/Records';
import { Pagination } from '@/components/ui/Pagination';
import { groupByDate, formatDateLabel } from '@/lib/groupByDate';
import { TrashIcon, PencilSquareIcon, CheckIcon, XMarkIcon, CalculatorIcon } from '@heroicons/react/24/outline';
import { Calculator } from '@/components/ui/Calculator';

interface PurchaseLine {
  id: number;
  item_id?: number;
  item_name?: string;
  qty: number;
  quantity?: string;
  unit_code: string;
  unit_label?: string;
  base_qty?: number;
  unit_price: number;
  total: number;
  unit_cost?: number | null;
  selling_price?: number | null;
  inner_units?: unknown[];
}

interface Purchase {
  id: number;
  transacted_on: string;
  total: number;
  lines: PurchaseLine[];
}

interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

interface DeleteTarget {
  purchaseId: number;
  lineId: number;
  itemName: string;
}

interface EditState {
  purchaseId: number;
  lineId: number;
  qty: string;
  unitCost: string;
  sellingPrice: string;
}

function money(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(value);
}

function lineCostPrice(line: PurchaseLine): string {
  return line.qty > 0 ? money(line.total / line.qty) : '—';
}

const iconButtonStyle: React.CSSProperties = {
  padding: 6,
  lineHeight: 0,
};

const filterInputClass = 'rounded border border-gray-300 px-2 py-1';

export default function PurchaseList() {
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = Math.max(1, Number(searchParams.get('page')) || 1);
  const search = searchParams.get('search') ?? '';
  const dateFrom = searchParams.get('date_from') ?? '';
  const dateTo = searchParams.get('date_to') ?? '';
  const hasFilters = search !== '' || dateFrom !== '' || dateTo !== '';

  const [filterForm, setFilterForm] = useState({ search, dateFrom, dateTo });
  const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
  const [editing, setEditing] = useState<EditState | null>(null);
  const [showPurchaseForm, setShowPurchaseForm] = useState(false);
  const [showCalculator, setShowCalculator] = useState(false);

  const params: Record<string, string | number> = { page };
  if (search) params.search = search;
  if (dateFrom) params.date_from = dateFrom;
  if (dateTo) params.date_to = dateTo;

  const { data: response, isLoading } = useQuery<PaginatedResponse<Purchase>>({
    queryKey: ['purchases', page, search, dateFrom, dateTo],
    queryFn: () => api.get('/purchases', { params }),
  });

  const purchases = response?.data || [];
  const meta = response?.meta;

  const deleteMutation = useMutation({
    mutationFn: ({ purchaseId, lineId }: DeleteTarget) =>
      api.delete(`/purchases/${purchaseId}/lines/${lineId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      toast.success('Line deleted');
      setDeleteTarget(null);
    },
    onError: (error: any) => toast.error(error.response?.data?.message || 'Failed to delete line'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ purchaseId, lineId, qty, unitCost, sellingPrice }: EditState) =>
      api.patch(`/purchases/${purchaseId}/lines/${lineId}`, {
        qty: Number(qty),
        unit_cost: Number(unitCost),
        selling_price: sellingPrice === '' ? null : Number(sellingPrice),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] });
      toast.success('Line updated');
      setEditing(null);
    },
    onError: (error: any) => {
      const payload = error?.response?.data;
      if (payload?.errors) {
        toast.error(Object.values(payload.errors).flat().join(' '));
      } else {
        toast.error(payload?.message || 'Failed to update line');
      }
    },
  });

  const handleDelete = (purchaseId: number, lineId: number, itemName: string) => {
    setDeleteTarget({ purchaseId, lineId, itemName });
  };

  const confirmDelete = () => {
    if (deleteTarget) {
      deleteMutation.mutate(deleteTarget);
    }
  };

  const startEdit = (purchaseId: number, line: PurchaseLine) => {
    const cost = line.unit_cost ?? line.unit_price ?? null;
    setEditing({
      purchaseId,
      lineId: line.id,
      qty: String(line.qty),
      unitCost: cost === null ? '' : String(cost),
      sellingPrice: line.selling_price === null || line.selling_price === undefined ? '' : String(line.selling_price),
    });
  };

  const saveEdit = () => {
    if (editing) {
      updateMutation.mutate(editing);
    }
  };

  const applyFilters = (event: FormEvent) => {
    event.preventDefault();
    const next = new URLSearchParams();
    next.set('page', '1');
    if (filterForm.search) next.set('search', filterForm.search);
    if (filterForm.dateFrom) next.set('date_from', filterForm.dateFrom);
    if (filterForm.dateTo) next.set('date_to', filterForm.dateTo);
    setSearchParams(next);
  };

  const clearFilters = () => {
    setFilterForm({ search: '', dateFrom: '', dateTo: '' });
    setSearchParams(new URLSearchParams());
  };

  const goToPage = (next: number) => {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('page', String(next));
    setSearchParams(nextParams);
  };

  return (
    <div className='p-6'>
      <div className='flex flex-wrap justify-between items-center gap-2 mb-6'>
        <h1 className='text-2xl font-bold'>Purchases</h1>
        <div className='flex items-center gap-2'>
          <button
            onClick={() => setShowCalculator(true)}
            className='btn alt inline-flex items-center gap-2'
          >
            <CalculatorIcon className='h-5 w-5' />
            Calculator
          </button>
          <button onClick={() => setShowPurchaseForm(true)} className='btn'>+ New Purchase</button>
        </div>
      </div>

      <form onSubmit={applyFilters} className='flex flex-wrap items-end gap-2 mb-3'>
        <label className='flex flex-col text-sm gap-1'>
          <span>Item name</span>
          <input
            type='search'
            value={filterForm.search}
            onChange={(event) => setFilterForm({ ...filterForm, search: event.target.value })}
            placeholder='Search items...'
            className={filterInputClass}
          />
        </label>
        <label className='flex flex-col text-sm gap-1'>
          <span>From</span>
          <input
            type='date'
            value={filterForm.dateFrom}
            onChange={(event) => setFilterForm({ ...filterForm, dateFrom: event.target.value })}
            className={filterInputClass}
          />
        </label>
        <label className='flex flex-col text-sm gap-1'>
          <span>To</span>
          <input
            type='date'
            value={filterForm.dateTo}
            onChange={(event) => setFilterForm({ ...filterForm, dateTo: event.target.value })}
            className={filterInputClass}
          />
        </label>
        <button type='submit' className='btn sm'>Filter</button>
        <button type='button' onClick={clearFilters} className='btn alt sm'>Clear</button>
      </form>

      {meta && meta.total > 0 && (
        <p className='text-sm text-gray-500 mb-3'>
          Showing {meta.from}–{meta.to} of {meta.total}
        </p>
      )}

      <div className='overflow-x-auto'>
        {isLoading ? (
          <div className='text-center p-8'>Loading...</div>
        ) : purchases.length === 0 ? (
          <div className='card p-8 text-center text-gray-600'>
            {hasFilters ? 'No purchases match your filters.' : 'Nothing yet.'}
          </div>
        ) : (
          <table className='w-full'>
            <thead>
              <tr className='border-b'>
                <th className='text-left p-3'>Purchase Total</th>
                <th className='text-left p-3'>Item</th>
                <th className='text-left p-3'>Qty</th>
                <th className='text-right p-3'>Cost Price</th>
                <th className='text-right p-3'>Line Total</th>
                <th className='text-right p-3'></th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(groupByDate(purchases)).map(([date, datePurchases]) => (
                <Fragment key={date}>
                  <tr className='bg-gray-50'>
                    <td colSpan={6} className='p-3 font-medium text-gray-700'>
                      {formatDateLabel(date)}
                    </td>
                  </tr>

                  {datePurchases.flatMap((purchase: Purchase) => {
                    const lines = purchase.lines || [];

                    if (lines.length === 0) {
                      return [
                        <tr key={purchase.id} className='border-b hover:bg-gray-50'>
                          <td className='p-3'>Total: {money(purchase.total)}</td>
                          <td className='p-3 text-gray-500' colSpan={5}>
                            No lines
                          </td>
                        </tr>,
                      ];
                    }

                    return lines.map((line, index) => {
                      const isEditing = editing?.lineId === line.id;
                      const editQty = editing ? Number(editing.qty) : 0;
                      const editCost = editing ? Number(editing.unitCost) : 0;
                      const editTotal =
                        Number.isFinite(editQty) && Number.isFinite(editCost) && editQty > 0
                          ? editQty * editCost
                          : 0;

                      return (
                        <tr key={line.id} className='border-b hover:bg-gray-50'>
                          {index === 0 && (
                            <td rowSpan={lines.length} className='p-3 align-top'>
                              <div className='totals-cell'>
                                <span className='total'>Total: {money(purchase.total)}</span>
                              </div>
                            </td>
                          )}
                          <td className='p-3'>{line.item_name}</td>

                          {isEditing ? (
                            <>
                              <td className='p-3 align-top'>
                                <input
                                  type='number'
                                  min={0}
                                  step='any'
                                  inputMode='decimal'
                                  className='w-24 rounded border border-gray-300 px-2 py-1'
                                  value={editing!.qty}
                                  onChange={(event) => setEditing({ ...editing!, qty: event.target.value })}
                                />
                              </td>
                              <td className='p-3 text-right align-top'>
                                <input
                                  type='number'
                                  min={0}
                                  step='any'
                                  inputMode='decimal'
                                  className='w-24 rounded border border-gray-300 px-2 py-1 text-right'
                                  value={editing!.unitCost}
                                  onChange={(event) => setEditing({ ...editing!, unitCost: event.target.value })}
                                />
                                <input
                                  type='number'
                                  min={0}
                                  step='any'
                                  inputMode='decimal'
                                  title='Selling price per unit (optional)'
                                  placeholder='Selling (optional)'
                                  className='mt-1 w-24 rounded border border-gray-300 px-2 py-1 text-right text-xs'
                                  value={editing!.sellingPrice}
                                  onChange={(event) => setEditing({ ...editing!, sellingPrice: event.target.value })}
                                />
                              </td>
                              <td className='p-3 text-right align-top'>{money(editTotal)}</td>
                              <td className='p-3 text-right align-top whitespace-nowrap'>
                                <button
                                  onClick={saveEdit}
                                  disabled={updateMutation.isPending}
                                  className='btn sm'
                                  style={iconButtonStyle}
                                  title='Save'
                                  aria-label='Save line'
                                >
                                  <CheckIcon className='h-4 w-4' />
                                </button>
                                <button
                                  onClick={() => setEditing(null)}
                                  disabled={updateMutation.isPending}
                                  className='btn alt sm ml-1'
                                  style={iconButtonStyle}
                                  title='Cancel'
                                  aria-label='Cancel edit'
                                >
                                  <XMarkIcon className='h-4 w-4' />
                                </button>
                              </td>
                            </>
                          ) : (
                            <>
                              <td className='p-3'>{line.quantity ?? line.qty}</td>
                              <td className='p-3 text-right'>{lineCostPrice(line)}</td>
                              <td className='p-3 text-right'>{money(line.total)}</td>
                              <td className='p-3 text-right whitespace-nowrap'>
                                <button
                                  onClick={() => startEdit(purchase.id, line)}
                                  className='btn alt sm'
                                  style={iconButtonStyle}
                                  title='Edit line'
                                  aria-label='Edit line'
                                >
                                  <PencilSquareIcon className='h-4 w-4' />
                                </button>
                                <button
                                  onClick={() => handleDelete(purchase.id, line.id, line.item_name ?? 'this line')}
                                  className='btn alt sm ml-1'
                                  style={{ ...iconButtonStyle, color: 'var(--bad)' }}
                                  title='Delete line'
                                  aria-label='Delete line'
                                >
                                  <TrashIcon className='h-4 w-4' />
                                </button>
                              </td>
                            </>
                          )}
                        </tr>
                      );
                    });
                  })}
                </Fragment>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {meta && (
        <Pagination page={page} lastPage={meta.last_page} onPageChange={goToPage} />
      )}

      <ConfirmDialog
        isOpen={!!deleteTarget}
        title='Delete Line'
        message={`Delete ${deleteTarget?.itemName} from the purchase ledger? Stock will be adjusted.`}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
        confirmText='Delete'
        variant='danger'
      />

      <RecordModal
        isOpen={showCalculator}
        title='Calculator'
        onClose={() => setShowCalculator(false)}
        panelClassName='calc-panel'
      >
        <Calculator />
      </RecordModal>

      <RecordModal
        isOpen={showPurchaseForm}
        title='Record a purchase'
        onClose={() => setShowPurchaseForm(false)}
      >
        <PurchaseForm
          onCancel={() => setShowPurchaseForm(false)}
          onSaved={(message) => {
            setShowPurchaseForm(false);
            toast.success(message);
          }}
        />
      </RecordModal>
    </div>
  );
}
