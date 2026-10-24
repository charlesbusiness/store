import { forwardRef } from 'react';

interface SaleLine {
  id: number;
  item_name?: string;
  item?: { name?: string };
  qty: number;
  quantity?: string;
  unit_code: string;
  unit_label?: string;
  unit?: { quantityLabel?: (qty: number | string) => string };
  unit_price: number;
  total: number;
}

interface Sale {
  id: number;
  transacted_on: string;
  total: number;
  lines: SaleLine[];
}

interface SaleReceiptPreviewProps {
  sale: Sale;
  customerName?: string;
}

function formatPlain(value: number): string {
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(value || 0));
}

function formatDate(date: string): string {
  const d = new Date(date);
  if (Number.isNaN(d.getTime())) return date;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function quantityLabel(line: SaleLine): string {
  if (line.quantity) return line.quantity;
  const qty = line.qty;
  const unit = line.unit;
  if (unit?.quantityLabel) {
    try {
      return unit.quantityLabel(qty);
    } catch {
      // ignore
    }
  }
  const label = line.unit_label || line.unit_code;
  return `${qty} ${label}`;
}

export const SaleReceiptPreview = forwardRef<HTMLDivElement, SaleReceiptPreviewProps>(
  function SaleReceiptPreview({ sale, customerName }, ref) {
    const name = customerName?.trim() || '';
    const date = formatDate(sale.transacted_on);

    return (
      <div
        ref={ref}
        data-receipt-preview
        style={{
          width: 400,
          background: '#ffffff',
          color: '#111827',
          fontFamily: '"DejaVu Sans", Arial, Helvetica, sans-serif',
          fontSize: 9,
          lineHeight: 1.38,
          padding: 20,
        }}
      >
        <div style={{ borderBottom: '2px solid #111827', paddingBottom: 10, marginBottom: 14 }}>
          <div style={{ fontSize: 16, fontWeight: 'bold', letterSpacing: 0.5 }}>Zaveb Horizon Integrated Solutions</div>
          <div
            style={{
              fontSize: 11,
              color: '#4b5563',
              marginTop: 2,
              textTransform: 'uppercase',
              letterSpacing: 1,
            }}
          >
            Sales Receipt
          </div>

          <table style={{ width: '100%', marginTop: 12, borderCollapse: 'collapse' }}>
            <tbody>
              {name !== '' && (
                <tr>
                  <td style={{ padding: '1px 0', color: '#6b7280', width: '45%' }}>Customer</td>
                  <td style={{ padding: '1px 0', textAlign: 'right', fontWeight: 'bold' }}>{name}</td>
                </tr>
              )}
              <tr>
                <td style={{ padding: '1px 0', color: '#6b7280', width: '45%' }}>Date</td>
                <td style={{ padding: '1px 0', textAlign: 'right', fontWeight: 'bold' }}>{date}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <table style={{ width: '100%', borderCollapse: 'collapse', tableLayout: 'fixed' }}>
          <colgroup>
            <col style={{ width: '46%' }} />
            <col style={{ width: '14%' }} />
            <col style={{ width: '20%' }} />
            <col style={{ width: '20%' }} />
          </colgroup>
          <thead>
            <tr>
              <th style={{ textAlign: 'left', fontSize: 8, textTransform: 'uppercase', letterSpacing: 0.4, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '4px 2px' }}>
                Item
              </th>
              <th style={{ textAlign: 'right', fontSize: 8, textTransform: 'uppercase', letterSpacing: 0.4, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '4px 2px', whiteSpace: 'nowrap' }}>
                Qty
              </th>
              <th style={{ textAlign: 'right', fontSize: 8, textTransform: 'uppercase', letterSpacing: 0.4, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '4px 2px', whiteSpace: 'nowrap' }}>
                Unit Price (₦)
              </th>
              <th style={{ textAlign: 'right', fontSize: 8, textTransform: 'uppercase', letterSpacing: 0.4, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '4px 2px', whiteSpace: 'nowrap' }}>
                Total (₦)
              </th>
            </tr>
          </thead>
          <tbody>
            {sale.lines.length === 0 ? (
              <tr>
                <td colSpan={4} style={{ padding: '4px 2px', textAlign: 'right', borderBottom: '1px solid #e5e7eb' }}>
                  No items.
                </td>
              </tr>
            ) : (
              sale.lines.map((line) => (
                <tr key={line.id}>
                  <td style={{ padding: '4px 2px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', wordBreak: 'break-word' }}>
                    {line.item_name || line.item?.name || `Item #${line.id}`}
                  </td>
                  <td style={{ padding: '4px 2px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right', whiteSpace: 'nowrap' }}>
                    {quantityLabel(line)}
                  </td>
                  <td style={{ padding: '4px 2px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right', whiteSpace: 'nowrap' }}>
                    {formatPlain(line.unit_price)}
                  </td>
                  <td style={{ padding: '4px 2px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right', whiteSpace: 'nowrap' }}>
                    {formatPlain(line.total)}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>

        <div style={{ marginTop: 14, borderTop: '2px solid #111827', paddingTop: 10, textAlign: 'right' }}>
          <div style={{ fontSize: 11, color: '#4b5563', textTransform: 'uppercase', letterSpacing: 1 }}>Total Sales (₦)</div>
          <div style={{ fontSize: 16, fontWeight: 'bold' }}>{formatPlain(sale.total)}</div>
        </div>

        <div style={{ marginTop: 18, textAlign: 'center', color: '#6b7280', fontSize: 10 }}>Thank you for your patronage.</div>
      </div>
    );
  },
);
