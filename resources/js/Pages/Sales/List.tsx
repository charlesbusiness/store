import { FormEvent, Fragment, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/Layout';
import { RecordModal, SaleForm } from '@/components/Records';
import { Pagination } from '@/components/ui/Pagination';
import { groupByDate, formatDateLabel } from '@/lib/groupByDate';
import { TrashIcon, PencilSquareIcon, CheckIcon, XMarkIcon, ArrowDownTrayIcon, ShareIcon } from '@heroicons/react/24/outline';
import { SaleReceiptPreview } from '@/components/Records/SaleReceiptPreview';
import { toPng } from 'html-to-image';
import { useRef } from 'react';

interface SaleLine {
  id: number;
  item_id?: number;
  item_name?: string;
  qty: number;
  quantity?: string;
  unit_code: string;
  unit_label?: string;
  base_qty: number;
  unit_price: number;
  total: number;
  unit_cost?: number | null;
  selling_price?: number | null;
  cost?: number | null;
  profit?: number | null;
  inner_units?: unknown[];
}

interface Sale {
  id: number;
  transacted_on: string;
  total: number;
  cost?: number | null;
  profit?: number | null;
  paid: boolean;
  lines: SaleLine[];
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
  saleId: number;
  lineId: number;
  itemName: string;
}

interface EditState {
  saleId: number;
  lineId: number;
  qty: string;
  unitPrice: string;
}

function money(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(value);
}

function sanitizeFilename(name: string): string {
  const cleaned = name.replace(/[^A-Za-z0-9 _.-]+/g, ' ').replace(/\s+/g, ' ').trim();
  if (cleaned === '') return 'receipt';
  return cleaned.slice(0, 80);
}

function lineCostPrice(line: SaleLine): string {
  return line.cost != null && line.qty > 0 ? money(line.cost / line.qty) : '—';
}

function lineSalesPrice(line: SaleLine): string {
  return line.qty > 0 ? money(line.total / line.qty) : '—';
}

function lineProfit(line: SaleLine): string {
  return line.profit != null ? money(line.profit) : '—';
}

const iconButtonStyle: React.CSSProperties = {
  padding: 6,
  lineHeight: 0,
};

const filterInputClass = 'rounded border border-gray-300 px-2 py-1';

export default function SaleList() {
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();

  const page = Math.max(1, Number(searchParams.get('page')) || 1);
  const search = searchParams.get('search') ?? '';
  const dateFrom = searchParams.get('date_from') ?? '';
  const dateTo = searchParams.get('date_to') ?? '';
  const statusParam = searchParams.get('status') ?? 'all';
  const status = ['all', 'pending', 'paid'].includes(statusParam) ? statusParam : 'all';
  const hasFilters = search !== '' || dateFrom !== '' || dateTo !== '' || status !== 'all';

  const [filterForm, setFilterForm] = useState({ search, dateFrom, dateTo, status });
  const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
  const [editing, setEditing] = useState<EditState | null>(null);
  const [showSaleForm, setShowSaleForm] = useState(false);
  const [receiptSale, setReceiptSale] = useState<Sale | null>(null);
  const [customerName, setCustomerName] = useState('');
  const [receiptFormat, setReceiptFormat] = useState<'pdf' | 'image'>('pdf');
  const [receiptBusy, setReceiptBusy] = useState(false);
  const receiptPreviewRef = useRef<HTMLDivElement | null>(null);

  const params: Record<string, string | number> = { page };
  if (search) params.search = search;
  if (dateFrom) params.date_from = dateFrom;
  if (dateTo) params.date_to = dateTo;
  if (status !== 'all') params.status = status;

  const { data: response, isLoading } = useQuery<PaginatedResponse<Sale>>({
    queryKey: ['sales', page, search, dateFrom, dateTo, status],
    queryFn: () => api.get('/sales', { params }),
  });

  const sales = response?.data || [];
  const meta = response?.meta;

  const deleteMutation = useMutation({
    mutationFn: ({ saleId, lineId }: DeleteTarget) =>
      api.delete(`/sales/${saleId}/lines/${lineId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales'] });
      toast.success('Line deleted');
      setDeleteTarget(null);
    },
    onError: (error: any) => toast.error(error.response?.data?.message || 'Failed to delete line'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ saleId, lineId, qty, unitPrice }: EditState) =>
      api.patch(`/sales/${saleId}/lines/${lineId}`, {
        qty: Number(qty),
        unit_price: Number(unitPrice),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales'] });
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

  const paidMutation = useMutation({
    mutationFn: (saleId: number) => api.patch(`/sales/${saleId}/paid`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales'] });
      toast.success('Sale marked as paid');
    },
    onError: (error: any) =>
      toast.error(error.response?.data?.message || 'Failed to mark sale as paid'),
  });

  const handleDelete = (saleId: number, lineId: number, itemName: string) => {
    setDeleteTarget({ saleId, lineId, itemName });
  };

  const confirmDelete = () => {
    if (deleteTarget) {
      deleteMutation.mutate(deleteTarget);
    }
  };

  const startEdit = (saleId: number, line: SaleLine) => {
    setEditing({
      saleId,
      lineId: line.id,
      qty: String(line.qty),
      unitPrice: String(line.unit_price),
    });
  };

  const saveEdit = () => {
    if (editing) {
      updateMutation.mutate(editing);
    }
  };

  const downloadReceiptPdf = async (saleId: number, name: string) => {
    const customerName = name.trim();
    const blob = await api.get<Blob, Blob>(`/sales/${saleId}/receipt`, {
      responseType: 'blob',
      params: customerName === '' ? {} : { customer_name: customerName },
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    const safeName = customerName !== '' ? sanitizeFilename(`sales-${customerName}`) : `receipt-sale-${saleId}`;
    link.download = `${safeName}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  const downloadReceiptImage = async (saleId: number, customerName?: string) => {
    const node = receiptPreviewRef.current;
    if (!node) throw new Error('preview not ready');
    const dataUrl = await toPng(node, { cacheBust: true, pixelRatio: 2 });
    const link = document.createElement('a');
    link.href = dataUrl;
    const name = (customerName || '').trim();
    const safeName = name !== '' ? sanitizeFilename(`sales-${name}`) : `receipt-sale-${saleId}`;
    link.download = `${safeName}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
  };

  const handleDownloadReceipt = async () => {
    if (!receiptSale || receiptBusy) return;
    setReceiptBusy(true);
    try {
      const name = customerName.trim();
      if (receiptFormat === 'pdf') {
        await downloadReceiptPdf(receiptSale.id, name);
      } else {
        await downloadReceiptImage(receiptSale.id, name);
      }
      toast.success('Receipt downloaded');
    } catch {
      toast.error('Failed to download receipt');
    } finally {
      setReceiptBusy(false);
    }
  };

  const handleShareReceipt = async () => {
    if (!receiptSale || receiptBusy) return;
    setReceiptBusy(true);
    const name = customerName.trim();
    const title = `Receipt #${receiptSale.id}`;
    const text = 'Your receipt';

    try {
      if (receiptFormat === 'image') {
        const node = receiptPreviewRef.current;
        if (!node) throw new Error('preview not ready');
        const dataUrl = await toPng(node, { cacheBust: true, pixelRatio: 2 });
        const res = await fetch(dataUrl);
        const blob = await res.blob();
        const fileName = name !== '' ? sanitizeFilename(`sales-${name}`) + '.png' : `receipt-sale-${receiptSale.id}.png`;
        const file = new File([blob], fileName, { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          await navigator.share({ title, text, files: [file] });
          return;
        }
      }

      if (receiptFormat === 'pdf') {
        const blob = await api.get<Blob, Blob>(`/sales/${receiptSale.id}/receipt`, {
          responseType: 'blob',
          params: name === '' ? {} : { customer_name: name },
        });
        const fileName = name !== '' ? sanitizeFilename(`sales-${name}`) + '.pdf' : `receipt-sale-${receiptSale.id}.pdf`;
        const file = new File([blob], fileName, { type: 'application/pdf' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          await navigator.share({ title, text, files: [file] });
          return;
        }
      }

      const response: any = await api.get(`/sales/${receiptSale.id}/receipt-link`, {
        params: name === '' ? {} : { customer_name: name },
      });
      const url = response.url || response.data?.url;
      if (navigator.share) {
        try {
          await navigator.share({ title, text, url });
          return;
        } catch (err: any) {
          if (err?.name === 'AbortError') return;
        }
      }
      await navigator.clipboard.writeText(url);
      toast.success('Share link copied');
    } catch (err: any) {
      if (err?.name === 'AbortError') return;
      toast.error('Failed to share receipt');
    } finally {
      setReceiptBusy(false);
    }
  };

  const applyFilters = (event: FormEvent) => {
    event.preventDefault();
    const next = new URLSearchParams();
    next.set('page', '1');
    if (filterForm.search) next.set('search', filterForm.search);
    if (filterForm.dateFrom) next.set('date_from', filterForm.dateFrom);
    if (filterForm.dateTo) next.set('date_to', filterForm.dateTo);
    if (filterForm.status !== 'all') next.set('status', filterForm.status);
    setSearchParams(next);
  };

  const clearFilters = () => {
    setFilterForm({ search: '', dateFrom: '', dateTo: '', status: 'all' });
    setSearchParams(new URLSearchParams());
  };

  const goToPage = (next: number) => {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('page', String(next));
    setSearchParams(nextParams);
  };

  return (
    <div className='p-6'>
      <div className='flex justify-between items-center mb-6'>
        <h1 className='text-2xl font-bold'>Sales</h1>
        <button onClick={() => setShowSaleForm(true)} className='btn'>+ New Sale</button>
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
        <label className='flex flex-col text-sm gap-1'>
          <span>Status</span>
          <select
            value={filterForm.status}
            onChange={(event) => setFilterForm({ ...filterForm, status: event.target.value })}
            className={filterInputClass}
          >
            <option value='all'>All</option>
            <option value='pending'>Pending</option>
            <option value='paid'>Paid</option>
          </select>
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
        ) : sales.length === 0 ? (
          <div className='card p-8 text-center text-gray-600'>
            {hasFilters ? 'No sales match your filters.' : 'Nothing yet.'}
          </div>
        ) : (
          <table className='w-full'>
            <thead>
              <tr className='border-b'>
                <th className='text-left p-3'>Sale Total</th>
                <th className='text-left p-3'>Item</th>
                <th className='text-left p-3'>Qty</th>
                <th className='text-right p-3'>Cost Price</th>
                <th className='text-right p-3'>Sales Price</th>
                <th className='text-right p-3'>Profit/Line</th>
                <th className='text-right p-3'>Line Total</th>
                <th className='text-right p-3'></th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(groupByDate(sales)).map(([date, dateSales]) => (
                <Fragment key={date}>
                  <tr className='bg-gray-50'>
                    <td colSpan={8} className='p-3 font-medium text-gray-700'>
                      {formatDateLabel(date)}
                    </td>
                  </tr>

                  {dateSales.flatMap((sale: Sale) => {
                    const lines = sale.lines || [];

                    if (lines.length === 0) {
                      return [
                        <tr key={sale.id} className='border-b hover:bg-gray-50'>
                          <td className='p-3'>Total: {money(sale.total)}</td>
                          <td className='p-3 text-gray-500' colSpan={7}>
                            No lines
                          </td>
                        </tr>,
                      ];
                    }

                    return lines.map((line, index) => {
                      const isEditing = editing?.lineId === line.id;
                      const editQty = editing ? Number(editing.qty) : 0;
                      const editPrice = editing ? Number(editing.unitPrice) : 0;
                      const editTotal =
                        Number.isFinite(editQty) && Number.isFinite(editPrice) && editQty > 0
                          ? editQty * editPrice
                          : 0;
                      const costPerUnit =
                        line.cost != null && line.qty > 0 ? line.cost / line.qty : null;
                      const editProfit =
                        costPerUnit !== null && Number.isFinite(editQty) && editQty > 0
                          ? (editPrice - costPerUnit) * editQty
                          : null;

                      return (
                        <tr key={line.id} className='border-b hover:bg-gray-50'>
                          {index === 0 && (
                            <td rowSpan={lines.length} className='p-3 align-top'>
                              <div className='totals-cell'>
                                <span className='total'>Total: {money(sale.total)}</span>
                                {sale.cost != null && (
                                  <>
                                    <div>Cost: {money(sale.cost)}</div>
                                    {sale.profit != null && <div>Profit: {money(sale.profit)}</div>}
                                  </>
                                )}
                                <div className='mt-2 flex items-center gap-2'>
                                  {sale.paid ? (
                                    <span className='badge badge-green'>Paid</span>
                                  ) : (
                                    <span className='badge badge-amber'>Pending</span>
                                  )}
                                  {!sale.paid && (
                                    <button
                                      onClick={() => paidMutation.mutate(sale.id)}
                                      disabled={paidMutation.isPending}
                                      className='btn alt sm'
                                      title='Mark this sale as paid'
                                    >
                                      Mark Paid
                                    </button>
                                  )}
                                </div>
                                <button
                                  onClick={() => {
                                    setCustomerName('');
                                    setReceiptFormat('pdf');
                                    setReceiptSale(sale);
                                  }}
                                  className='btn alt sm mt-2 inline-flex items-center gap-1'
                                  title='Receipt'
                                  aria-label='Receipt'
                                >
                                  <ArrowDownTrayIcon className='h-4 w-4' />
                                  Receipt
                                </button>
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
                                {costPerUnit !== null ? money(costPerUnit) : '—'}
                              </td>
                              <td className='p-3 text-right align-top'>
                                <input
                                  type='number'
                                  min={0}
                                  step='any'
                                  inputMode='decimal'
                                  className='w-24 rounded border border-gray-300 px-2 py-1 text-right'
                                  value={editing!.unitPrice}
                                  onChange={(event) => setEditing({ ...editing!, unitPrice: event.target.value })}
                                />
                              </td>
                              <td className='p-3 text-right align-top'>
                                {editProfit !== null ? money(editProfit) : '—'}
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
                              <td className='p-3 text-right'>{lineSalesPrice(line)}</td>
                              <td className='p-3 text-right'>{lineProfit(line)}</td>
                              <td className='p-3 text-right'>{money(line.total)}</td>
                              <td className='p-3 text-right whitespace-nowrap'>
                                <button
                                  onClick={() => startEdit(sale.id, line)}
                                  className='btn alt sm'
                                  style={iconButtonStyle}
                                  title='Edit line'
                                  aria-label='Edit line'
                                >
                                  <PencilSquareIcon className='h-4 w-4' />
                                </button>
                                <button
                                  onClick={() => handleDelete(sale.id, line.id, line.item_name ?? 'this line')}
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
        message={`Delete ${deleteTarget?.itemName} from the sale ledger? Stock will be adjusted.`}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
        confirmText='Delete'
        variant='danger'
      />

      <RecordModal
        isOpen={receiptSale !== null}
        title='Receipt'
        onClose={() => {
          if (!receiptBusy) {
            setReceiptSale(null);
          }
        }}
      >
        {receiptSale && (
          <div className='space-y-4'>
            <label>
              <span>Customer name (optional)</span>
              <input
                type='text'
                value={customerName}
                onChange={(event) => setCustomerName(event.target.value)}
                maxLength={120}
                placeholder='e.g. Ada Obi'
              />
            </label>

            <div className='flex items-center justify-between'>
              <span className='text-sm text-gray-600'>Format</span>
              <div className='flex rounded border border-gray-300 overflow-hidden'>
                <button
                  type='button'
                  onClick={() => setReceiptFormat('pdf')}
                  className={`px-3 py-1 text-sm ${receiptFormat === 'pdf' ? 'bg-gray-900 text-white' : 'bg-white'}`}
                >
                  PDF
                </button>
                <button
                  type='button'
                  onClick={() => setReceiptFormat('image')}
                  className={`px-3 py-1 text-sm ${receiptFormat === 'image' ? 'bg-gray-900 text-white' : 'bg-white'}`}
                >
                  Image
                </button>
              </div>
            </div>

            <div className='flex justify-center overflow-auto rounded border border-gray-200 bg-gray-50 p-4'>
              <SaleReceiptPreview ref={receiptPreviewRef} sale={receiptSale} customerName={customerName} />
            </div>

            <div className='flex justify-end gap-2'>
              <button
                type='button'
                onClick={() => setReceiptSale(null)}
                disabled={receiptBusy}
                className='btn alt'
              >
                Cancel
              </button>
              <button type='button' onClick={handleShareReceipt} disabled={receiptBusy} className='btn alt inline-flex items-center gap-1'>
                <ShareIcon className='h-4 w-4' />
                {receiptBusy ? 'Working...' : 'Share'}
              </button>
              <button type='button' onClick={handleDownloadReceipt} disabled={receiptBusy} className='btn inline-flex items-center gap-1'>
                <ArrowDownTrayIcon className='h-4 w-4' />
                {receiptBusy ? 'Working...' : 'Download'}
              </button>
            </div>
          </div>
        )}
      </RecordModal>

      <RecordModal
        isOpen={showSaleForm}
        title='Record a sale'
        onClose={() => setShowSaleForm(false)}
      >
        <SaleForm
          onCancel={() => setShowSaleForm(false)}
          onSaved={(message) => {
            setShowSaleForm(false);
            toast.success(message);
          }}
        />
      </RecordModal>
    </div>
  );
}
