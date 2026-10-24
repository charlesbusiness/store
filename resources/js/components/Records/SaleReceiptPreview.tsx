import { forwardRef } from 'react';
function money(value: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'NGN' }).format(value);
}


interface SaleLine {
  id: number;
  item_name?: string;
  qty: number;
  quantity?: string;
  unit_code: string;
  unit_label?: string;
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

export const SaleReceiptPreview = forwardRef<HTMLDivElement, SaleReceiptPreviewProps>(
  function SaleReceiptPreview({ sale, customerName }, ref) {
    const name = customerName?.trim() || '';

    return (
      <div
        ref={ref}
        data-receipt-preview
        style={{
          width: 302,
          background: '#ffffff',
          color: '#111111',
          fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
          fontSize: 10,
          lineHeight: 1.4,
          padding: 18,
          border: '1px solid #d9d9d9',
          boxShadow: 'inset 0 0 0 1px rgba(17,17,17,0.02)',
        }}
      >
        <div style={{ textAlign: 'center', marginBottom: 10 }}>
          <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: 0.5 }}>RECEIPT</div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
          <span>Date</span>
          <span>{sale.transacted_on}</span>
        </div>

        {name !== '' && (
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
            <span>Customer</span>
            <span style={{ textAlign: 'right', maxWidth: 170 }}>{name}</span>
          </div>
        )}

        <div style={{ borderTop: '1px dashed #bfbfbf', margin: '8px 0' }} />

        <div style={{ marginBottom: 6 }}>
          {sale.lines.map((line) => {
            const qty = line.quantity ?? String(line.qty);
            const unitLabel = line.unit_label || line.unit_code;
            const unitPrice = line.unit_price;

            return (
              <div key={line.id} style={{ marginBottom: 8 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                  <span style={{ flex: 1 }}>{line.item_name}</span>
                  <span>{money(line.total)}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', color: '#4f4f4f' }}>
                  <span>
                    {qty} {unitLabel} @ {money(unitPrice)}
                  </span>
                </div>
              </div>
            );
          })}
        </div>

        <div style={{ borderTop: '1px dashed #bfbfbf', margin: '10px 0' }} />

        <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700 }}>
          <span>Total</span>
          <span>{money(sale.total)}</span>
        </div>
      </div>
    );
  },
);
