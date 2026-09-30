import { useEffect, useState } from 'react';
import { Building2, ChevronLeft, Pencil, Printer, ReceiptText, ShoppingCart, Trash2, User, UtensilsCrossed } from 'lucide-react';
import { formatIDR } from '../../api/client';
import { isDraftOrder, isUnpaidOrder, receivedOf, remainingOf } from '../../utils/order';
import Price, { PriceRow } from '../Price';
import ProductLinesList, { LineThumb } from './ProductLinesList';
import TransactionCorrection from './TransactionCorrection';
import { PaymentButton, TOTAL_VALUE, TransactionCard } from './PaymentParts';
import ViewModeSwitch from '../ViewModeSwitch';

/**
 * Halaman detail satu order dari tab Pesanan.
 *
 * Tampilannya penuh seperti tab "Cek Pesanan": daftar produk di kiri dan panel
 * "Transaksi" di kanan. Produk selalu readOnly di sini — mengedit draft
 * dilakukan lewat tombol "Lanjutkan Draft" yang membuka draft di halaman utama
 * kasir, tempat menu bisa ditambah, diubah, lalu disimpan atau dikirim ke dapur.
 *
 * Aksi mengikuti status order. Draft bisa dilanjutkan ke editor kasir atau
 * dihapus; order yang belum lunas hanya menerima pembayaran sisa tagihan.
 * Order non-draft tidak pernah menampilkan tombol simpan atau draft, tapi
 * pemegang `transaction.correct` (Admin/Supervisor) mendapat tombol "Koreksi
 * Transaksi" untuk memperbaiki isi pesanan yang salah input.
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
    onContinueDraft,
    onDelete = null,
    canEdit = false,
    onEdit = null,
    onPrint,
    onClose,
    busy = false,
    correction = null,
}) {
    // Form pembayaran tertutup sejak awal supaya panel Transaksi tetap ringkas.
    const [payOpen, setPayOpen] = useState(false);
    // Nominal pelunasan yang diinput kasir. Kosong berarti pakai sisa tagihan.
    const [settleRaw, setSettleRaw] = useState('');

    const draft = isDraftOrder(order);
    const received = receivedOf(order);
    const total = Number(order?.total_amount ?? 0);
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
    }, [order?.id, remaining]);

    // Hooks harus dijalankan tanpa syarat: return di bawah baru dilakukan
    // setelah semua hook selesai, kalau tidak React akan menghitung jumlah hook
    // berbeda antar render dan melempar error.
    if (!order) return null;

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

    const voided = order.status === 'void';
    const refunded = order.payment_status === 'refunded';

    const status = voided
        ? { text: 'Dibatalkan', cls: 'badge-unpaid' }
        : draft
          ? { text: 'Draft', cls: 'badge-unpaid' }
          : refunded
            ? { text: 'Diretur', cls: 'badge-unpaid' }
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
                    {!draft && canEdit && !voided && (
                        <button
                            onClick={onEdit}
                            className="btn btn-secondary"
                            title="Perbaiki isi transaksi yang salah input (menu, jumlah, meja, diskon). Pembayaran dan stok tidak berubah."
                        >
                            <Pencil size={15} /> Koreksi Transaksi
                        </button>
                    )}
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

                        {order.notes && (
                            <div>
                                <span className="label">Catatan</span>
                                <div className="input !py-2 text-sm whitespace-pre-wrap">
                                    {order.notes}
                                </div>
                            </div>
                        )}

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

                        {draft ? (
                            <div className="mt-3 flex items-center gap-2">
                                <button
                                    onClick={onContinueDraft}
                                    disabled={busy}
                                    title="Buka draft di halaman kasir untuk menambah atau mengubah menu, lalu simpan atau lanjutkan ke dapur"
                                    className="btn btn-primary flex-1 justify-center"
                                >
                                    {busy ? 'Memproses...' : <><ShoppingCart size={15} /> Lanjutkan Draft</>}
                                </button>
                                {onDelete && (
                                    <button
                                        onClick={onDelete}
                                        disabled={busy}
                                        title="Hapus draft ini — pesanan tidak akan pernah diproses"
                                        className="btn btn-ghost !px-3 justify-center"
                                    >
                                        <Trash2 size={15} />
                                    </button>
                                )}
                            </div>
                        ) : (
                            <>
                                <PaymentButton
                                    open={payOpen}
                                    onToggle={() => setPayOpen((open) => !open)}
                                    methods={payMethods}
                                    method={method}
                                    onMethodChange={onMethodChange}
                                    qrisId={method === 'qris' ? qrisId : null}
                                    note={
                                        unpaid ? (
                                            <p className="text-[11px] text-muted">
                                                Sisa tagihan{' '}
                                                <span className="font-semibold text-content tabular-nums">{formatIDR(remaining)}</span>
                                            </p>
                                        ) : null
                                    }
                                    amount={
                                        unpaid
                                            ? { raw: settleRaw, onChange: setSettleRaw, placeholder: String(remaining) }
                                            : { raw: String(received), onChange: () => {}, disabled: true }
                                    }
                                    status={status}
                                    change={settleChange}
                                    summary={<Price value={received} className="text-sm shrink-0" amountClassName="font-semibold" />}
                                />

                                {unpaid && (
                                    <button
                                        onClick={() => onSettle(settleAmount)}
                                        disabled={busy || settleAmount <= 0}
                                        title="Terima pembayaran sebesar nominal di atas"
                                        className="btn btn-success w-full justify-center mt-3"
                                    >
                                        {busy ? 'Memproses...' : 'Terima Pelunasan'}
                                    </button>
                                )}
                            </>
                        )}

                        {!draft && (
                            <TransactionCorrection
                                order={order}
                                canVoid={correction?.canVoid ?? false}
                                canRefund={correction?.canRefund ?? false}
                                onVoid={correction?.onVoid}
                                onRefund={correction?.onRefund}
                                busy={busy}
                            />
                        )}
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
