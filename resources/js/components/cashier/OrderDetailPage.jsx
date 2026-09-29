import { useEffect, useState } from 'react';
import { Building2, ChevronLeft, Printer, ReceiptText, ShoppingCart, User, UtensilsCrossed } from 'lucide-react';
import { formatIDR } from '../../api/client';
import { isDraftOrder, isUnpaidOrder, receivedOf, remainingOf } from '../../utils/order';
import Price, { PriceRow } from '../Price';
import ProductLinesList, { LineThumb } from './ProductLinesList';
import { PaymentDropdown, TOTAL_VALUE, TransactionCard } from './PaymentParts';
import ViewModeSwitch from '../ViewModeSwitch';

/**
 * Halaman detail satu order dari tab Pesanan.
 *
 * Tampilannya penuh seperti tab "Cek Pesanan": daftar produk di kiri dan panel
 * "Transaksi" di kanan dengan cangkang,Meja, Pelanggan, Rincian Pesanan, total,
 * metode, rekening, QRIS, dan Nominal yang sama persis. Bedanya hanya pada
 * sifat datanya:
 *
 * - Cek Pesanan    -> keranjang aktif, bisa diubah dan dibayar di tempat.
 * - Detail Pesanan -> order tersimpan, produk readOnly dan pembayaran hanya
 *   menampilkan riwayat, bukan menerima input baru.
 *
 * Tombol cetak struk tidak ada di panel, melainkan di header paling kanan.
 *
 * Aksi mengikuti status order. Draft bisa dilanjutkan menjadi faktur dan
 * dikirim ke dapur tanpa pembayaran; order yang belum lunas hanya menerima
 * pelunasan. Order non-draft tidak pernah menampilkan tombol simpan atau draft.
 */
export default function OrderDetailPage({
    order,
    view = 'grid',
    onViewChange,
    payMethods = [],
    method,
    onMethodChange,
    qrisId = null,
    onSettle,
    onFinalize,
    onPrint,
    onClose,
    busy = false,
}) {
    if (!order) return null;

    // Dropdown pembayaran tertutup sejak awal supaya panel Transaksi tetap
    // ringkas; kasir membukanya lewat tombol "Bayar" bila perlu mengisi
    // nominal atau mengganti metode.
    const [payOpen, setPayOpen] = useState(false);
    // Nominal pelunasan yang diinput kasir. Kosong berarti pakai sisa tagihan.
    const [settleRaw, setSettleRaw] = useState('');

    const draft = isDraftOrder(order);
    const received = receivedOf(order);
    const total = Number(order.total_amount);
    const remaining = remainingOf(order);
    const unpaid = isUnpaidOrder(order);

    // Nominal pelunasan: sisa tagihan secara default, bisa dikurangi untuk
    // bayar sebagian atau ditambah sampai lebih dari sisa untuk kembalian.
    const settleAmount = settleRaw === '' ? remaining : Number(settleRaw);
    const settleChange = Math.max(0, settleAmount - remaining);

    // Nominal kembali ke sisa tagihan setiap kali berganti order atau sisa
    // tagihannya berubah, misalnya setelah pelunasan diterima.
    useEffect(() => {
        setSettleRaw('');
    }, [order.id, remaining]);
    const customer = order.customer ?? null;
    const customerLabel = customer ? customer.company_name || customer.name : 'Pelanggan Umum';
    const createdAt = order.created_at
        ? new Date(order.created_at).toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '-';

    const items = order.items ?? [];
    const lines = items.map((item) => ({
        product_id: item.id,
        name: item.product?.name ?? '-',
        price: Number(item.price),
        qty: Number(item.qty),
        note: item.note,
        status: item.status,
        product: item.product ?? null,
    }));

    const status = draft
        ? { text: 'Draft', cls: 'badge-unpaid' }
        : remaining > 0
          ? { text: 'Belum Lunas', cls: 'badge-unpaid' }
          : { text: 'Lunas', cls: 'badge-paid' };

    return (
        <div>
            <div className="card mb-4 flex items-center gap-3">
                <button
                    onClick={onClose}
                    className="btn-icon bg-surface-2"
                    title="Kembali ke daftar pesanan"
                >
                    <ChevronLeft size={16} />
                </button>
                <div className="min-w-0">
                    <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                        <ReceiptText size={16} /> Detail Pesanan
                        <span className={`badge ${status.cls}`}>{status.text}</span>
                    </h3>
                    <p className="text-[11px] text-muted truncate">
                        {order.order_number ?? '-'} &middot;{' '}
                        {order.invoice?.invoice_number ?? 'Belum ada faktur'} &middot; {createdAt}
                    </p>
                </div>

                <div className="ml-auto flex items-center gap-2">
                    {!draft && (
                        <button
                            onClick={onPrint}
                            className="btn btn-secondary"
                            title="Cetak struk"
                        >
                            <Printer size={15} /> Cetak Struk
                        </button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2">
                    <div className="card">
                        <div className="flex items-center justify-between gap-3 pb-3 mb-4">
                            <h4 className="font-bold text-sm uppercase tracking-wide">
                                Daftar Produk
                            </h4>
                            <div className="flex items-center gap-2">
                                <span className="badge badge-pending">{lines.length} item</span>
                                <ViewModeSwitch value={view} onChange={onViewChange} />
                            </div>
                        </div>

                        <ProductLinesList view={view} lines={lines} readOnly />
                    </div>
                </div>

                <TransactionCard title="Transaksi" icon={ShoppingCart}>
                    <div className="mt-3 space-y-3 pr-0.5">
                        <div>
                            <span className="label">Meja</span>
                            <div className="input !py-2 flex items-center gap-2">
                                <UtensilsCrossed size={14} className="text-faint shrink-0" />
                                <span className="truncate">{order.table_number ?? '-'}</span>
                            </div>
                        </div>

                        <div>
                            <span className="label">Pelanggan</span>
                            <div className="input !py-2 flex items-center gap-2">
                                {customer?.customer_type === 'business' ? (
                                    <Building2 size={14} className="text-faint shrink-0" />
                                ) : (
                                    <User size={14} className="text-faint shrink-0" />
                                )}
                                <span className="truncate">{customerLabel}</span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <span className="label !mb-0">Rincian Pesanan</span>
                            <span className="badge badge-pending">{items.length} item</span>
                        </div>
                        <div className="space-y-2">
                            {items.map((item) => (
                                <div key={item.id} className="flex items-center gap-2 bg-surface-2 rounded-xl p-2">
                                    <LineThumb product={item.product} />
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-semibold truncate">
                                            {item.product?.name ?? '-'}
                                        </div>
                                        <div className="text-[11px] text-muted tabular-nums">
                                            {formatIDR(item.price)} &times; {item.qty}
                                        </div>
                                    </div>
                                    <PriceRow
                                        value={Number(item.price) * Number(item.qty)}
                                        className="shrink-0"
                                        amountClassName="text-accent font-semibold"
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-4 pt-3">
                        <div className="space-y-1.5">
                            <TotalRow label="Subtotal" value={order.subtotal} />
                            {Number(order.discount) > 0 && (
                                <TotalRow label="Diskon" value={-Number(order.discount)} />
                            )}
                            {Number(order.tax_amount) > 0 && (
                                <TotalRow label="Pajak" value={order.tax_amount} />
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-3 mt-2 pt-2">
                            <span className="text-sm font-bold">Grand Total</span>
                            <PriceRow
                                value={total}
                                className={`${TOTAL_VALUE} text-xl`}
                                symbolClassName="font-bold"
                                amountClassName="font-bold"
                            />
                        </div>

                        <PaymentDropdown
                            open={payOpen}
                            onToggle={() => setPayOpen((open) => !open)}
                            methods={draft ? [] : payMethods}
                            method={method}
                            onMethodChange={onMethodChange}
                            qrisId={!draft && method === 'qris' ? qrisId : null}
                            note={
                                draft ? (
                                    <p className="text-[11px] text-muted">Belum ada metode pembayaran.</p>
                                ) : unpaid ? (
                                    <p className="text-[11px] text-muted">
                                        Sisa tagihan{' '}
                                        <span className="font-semibold text-content tabular-nums">{formatIDR(remaining)}</span>
                                    </p>
                                ) : null
                            }
                            amount={
                                draft
                                    ? null
                                    : unpaid
                                      ? { raw: settleRaw, onChange: setSettleRaw, placeholder: String(remaining) }
                                      : { raw: String(received), onChange: () => {}, disabled: true }
                            }
                            status={status}
                            change={settleChange}
                            summary={<Price value={received} className="text-sm shrink-0" amountClassName="font-semibold" />}
                        />

                        {draft ? (
                            <button
                                onClick={onFinalize}
                                disabled={busy}
                                title="Terbitkan faktur dan kirim ke dapur tanpa pembayaran"
                                className="btn btn-primary w-full justify-center mt-3"
                            >
                                {busy ? 'Memproses...' : 'Lanjutkan Draft'}
                            </button>
                        ) : unpaid ? (
                            <button
                                onClick={() => onSettle(settleAmount)}
                                disabled={busy || settleAmount <= 0}
                                title="Terima pembayaran sebesar nominal di atas"
                                className="btn btn-success w-full justify-center mt-3"
                            >
                                {busy ? 'Memproses...' : 'Terima Pelunasan'}
                            </button>
                        ) : null}
                    </div>
                </TransactionCard>
            </div>
        </div>
    );
}

function TotalRow({ label, value, emphasis = false, tone = '' }) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className={emphasis ? 'font-bold' : 'text-muted'}>{label}</span>
            <PriceRow
                value={value}
                className={TOTAL_VALUE}
                symbolClassName={emphasis ? '' : 'text-muted'}
                amountClassName={`${emphasis ? 'font-bold' : 'font-semibold'} ${tone}`}
            />
        </div>
    );
}
