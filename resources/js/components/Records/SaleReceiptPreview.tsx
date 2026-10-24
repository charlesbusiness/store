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

function money(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(value);
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
          width: 380,
          background: '#ffffff',
          color: '#111827',
          fontFamily: '"DejaVu Sans", Arial, Helvetica, sans-serif',
          fontSize: 11,
          lineHeight: 1.45,
          padding: 24,
        }}
      >
        <div style={{ borderBottom: '2px solid #111827', paddingBottom: 12, marginBottom: 16 }}>
          <div style={{ fontSize: 20, fontWeight: 'bold', letterSpacing: 0.5 }}>Zaveb Horizon Integrated Solutions</div>
          <div
            style={{
              fontSize: 12,
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

        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th style={{ textAlign: 'left', fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.5, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '6px 4px' }}>
                Item
              </th>
              <th style={{ textAlign: 'left', fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.5, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '6px 4px' }}>
                Date
              </th>
              <th style={{ textAlign: 'right', fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.5, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '6px 4px' }}>
                Qty
              </th>
              <th style={{ textAlign: 'right', fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.5, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '6px 4px' }}>
                Unit Price
              </th>
              <th style={{ textAlign: 'right', fontSize: 9, textTransform: 'uppercase', letterSpacing: 0.5, color: '#6b7280', borderBottom: '1px solid #d1d5db', padding: '6px 4px' }}>
                Total
              </th>
            </tr>
          </thead>
          <tbody>
            {sale.lines.length === 0 ? (
              <tr>
                <td colSpan={5} style={{ padding: '7px 4px', textAlign: 'right', borderBottom: '1px solid #e5e7eb' }}>
                  No items.
                </td>
              </tr>
            ) : (
              sale.lines.map((line) => (
                <tr key={line.id}>
                  <td style={{ padding: '7px 4px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top' }}>
                    {line.item_name || line.item?.name || `Item #${line.id}`}
                  </td>
                  <td style={{ padding: '7px 4px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', whiteSpace: 'nowrap' }}>
                    {date}
                  </td>
                  <td style={{ padding: '7px 4px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right', whiteSpace: 'nowrap' }}>
                    {quantityLabel(line)}
                  </td>
                  <td style={{ padding: '7px 4px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right' }}>
                    {money(line.unit_price)}
                  </td>
                  <td style={{ padding: '7px 4px', borderBottom: '1px solid #e5e7eb', verticalAlign: 'top', textAlign: 'right' }}>
                    {money(line.total)}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>

        <div style={{ marginTop: 16, borderTop: '2px solid #111827', paddingTop: 10, textAlign: 'right' }}>
          <div style={{ fontSize: 11, color: '#4b5563', textTransform: 'uppercase', letterSpacing: 1 }}>Total Sales</div>
          <div style={{ fontSize: 18, fontWeight: 'bold' }}>{money(sale.total)}</div>
        </div>

        <div style={{ marginTop: 22, textAlign: 'center', color: '#6b7280', fontSize: 10 }}>Thank you for your patronage.</div>
      </div>
    );
  },
);
