import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Banknote, CreditCard, Landmark, Printer, ReceiptText, CheckCircle2,
    Minus, Plus, Trash2, ShoppingCart, Send, QrCode, UtensilsCrossed, ScrollText, AlertCircle,
    Save, ChevronDown, ChevronLeft,
} from 'lucide-react';
import Layout from '../components/Layout';
import ProductCatalog from '../components/ProductCatalog';
import QrisQrCode from '../components/QrisQrCode';
import CurrencyInput from '../components/CurrencyInput';
import { PayMethodBadge } from '../components/badges';
import { api, formatIDR, parseNumber } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifySuccess, notifyError } from '../utils/alerts';

const NAV_ICONS = { orders: UtensilsCrossed, draft: ReceiptText, history: ScrollText };

export default function CashierDashboard() {
    const queryClient = useQueryClient();

    const [tab, setTab] = useState('orders');
    const [mode, setMode] = useState('grid');
    const [query, setQuery] = useState('');
    const [cart, setCart] = useState([]);
    const [table, setTable] = useState('');
    const [discountType, setDiscountType] = useState('percent');
    const [discountRaw, setDiscountRaw] = useState('');
    const [taxRate, setTaxRate] = useState(11);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [paidRaw, setPaidRaw] = useState('');
    const [paidTouched, setPaidTouched] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [selected, setSelected] = useState({});

    const { data: products = [] } = useQuery({
        queryKey: ['products'],
        queryFn: async () => (await api.get('/products')).data.data,
    });

    const { data: settings } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => (await api.get('/settings')).data.data,
    });

    const { data: pending = [] } = useQuery({
        queryKey: ['cashier-pending'],
        queryFn: async () => (await api.get('/payments/pending')).data.data,
        refetchInterval: 15_000,
    });

    const { data: today = [] } = useQuery({
        queryKey: ['cashier-today'],
        queryFn: async () => (await api.get('/payments/today')).data.data,
        refetchInterval: 15_000,
    });

    const flags = settings?.cashier ?? {};
    const showFavorites = flags.cashier_show_favorites ?? true;
    const showStock = flags.cashier_show_stock ?? true;
    const enableTable = flags.cashier_enable_table ?? true;
    const enablePpn = flags.cashier_enable_ppn ?? true;
    const enablePrepay = flags.cashier_enable_prepay ?? false;
    const tableNumbers = settings?.table_numbers ?? Array.from({ length: 20 }, (_, i) => String(i + 1));

    useEffect(() => {
        if (settings) {
            setTaxRate(Number(settings.ppn_rate ?? 11));
            const methods = settings.payment_methods ?? [];
            if (methods.length && !methods.some((m) => m.code === paymentMethod)) {
                setPaymentMethod(methods[0].code);
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [settings]);

    useEffect(() => {
        const channel = listenToOrders({
            onOrderCreated: () => queryClient.invalidateQueries({ queryKey: ['cashier-pending'] }),
            onPaymentProcessed: () => {
                queryClient.invalidateQueries({ queryKey: ['cashier-pending'] });
                queryClient.invalidateQueries({ queryKey: ['cashier-today'] });
            },
        });
        return () => {
            channel?.stopListening?.('order.created');
            channel?.stopListening?.('payment.processed');
        };
    }, [queryClient]);

    const methods = settings?.payment_methods ?? [];
    const payMethods = methods.length > 0 ? methods : [{ code: 'cash', name: 'Tunai', mdr_rate: 0 }];

    const totals = useMemo(() => {
        const subtotal = cart.reduce((sum, l) => sum + l.price * l.qty, 0);
        const discAmount = discountType === 'percent'
            ? (subtotal * (parseNumber(discountRaw) || 0)) / 100
            : Math.min(parseNumber(discountRaw), subtotal);
        const discount = Math.min(discAmount, subtotal);
        const base = subtotal - discount;
        const tax = enablePpn ? (base * (Number(taxRate) || 0)) / 100 : 0;
        return { subtotal, discount, base, tax, total: base + tax };
    }, [cart, discountType, discountRaw, taxRate, enablePpn]);

    const paid = parseNumber(paidRaw);
    const total = totals.total;
    const change = paid >= total ? paid - total : 0;
    const remaining = paid < total ? total - paid : 0;
    const isLunas = paid >= total && total > 0;
    const canSubmit = cart.length > 0 && (!enableTable || table !== '') && (enablePrepay ? paid > 0 : isLunas) && !submitting;
    const canDraft = cart.length > 0 && (!enableTable || table !== '') && !submitting;

    useEffect(() => {
        if (!paidTouched && total > 0) {
            setPaidRaw(String(Math.round(total)));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [total, paidTouched, cart.length]);

    const totalToday = today.reduce((sum, r) => sum + Number(r.net_amount), 0);

    const addToCart = (product, qty = 1) => {
        setCart((prev) => {
            const existing = prev.find((l) => l.product_id === product.id);
            if (existing) {
                return prev.map((l) => (l.product_id === product.id ? { ...l, qty: l.qty + qty } : l));
            }
            return [...prev, { product_id: product.id, name: product.name, price: Number(product.price), qty }];
        });
    };

    const updateQty = (id, delta) =>
        setCart((prev) =>
            prev
                .map((l) => (l.product_id === id ? { ...l, qty: Math.max(0, l.qty + delta) } : l))
                .filter((l) => l.qty > 0),
        );

    const removeLine = (id) => setCart((prev) => prev.filter((l) => l.product_id !== id));

    const resetCheckout = () => {
        setCart([]);
        setTable('');
        setDiscountRaw('');
        setDiscountType('percent');
        setPaidRaw('');
        setPaidTouched(false);
    };

    const submitOrder = async (paymentType = 'pay_now') => {
        if (paymentType === 'pay_now' ? !canSubmit : !canDraft) return;
        setSubmitting(true);
        try {
            await api.post('/orders', {
                table_number: enableTable ? table : undefined,
                payment_type: paymentType,
                discount: totals.discount,
                tax_rate: enablePpn ? Number(taxRate) || 0 : 0,
                payment_method: paymentType === 'pay_now' ? paymentMethod : undefined,
                paid_amount: paymentType === 'pay_now' ? paid : undefined,
                items: cart.map(({ product_id, qty }) => ({ product_id, qty })),
            });
            resetCheckout();
            notifySuccess(
                paymentType === 'pay_now'
                    ? 'Transaksi lunas, pesanan dikirim ke dapur.'
                    : 'Transaksi disimpan sebagai draft (faktur gantung menunggu pelunasan).',
            );
            queryClient.invalidateQueries({ queryKey: ['cashier-pending'] });
            queryClient.invalidateQueries({ queryKey: ['cashier-today'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
        } catch (err) {
            notifyError('Transaksi gagal', err.response?.data?.message ?? 'Gagal memproses transaksi.');
        } finally {
            setSubmitting(false);
        }
    };

    const settle = async (invoice) => {
        const method = selected[invoice.order.id] ?? payMethods[0]?.code ?? 'cash';
        try {
            await api.post(`/payments/orders/${invoice.order.id}/settle`, { payment_method: method });
            notifySuccess(`Pembayaran ${invoice.invoice_number} berhasil.`);
            queryClient.invalidateQueries({ queryKey: ['cashier-pending'] });
            queryClient.invalidateQueries({ queryKey: ['cashier-today'] });
        } catch (err) {
            notifyError('Pembayaran gagal', err.response?.data?.message ?? 'Gagal memproses pembayaran.');
        }
    };

    const printReceipt = (order) => {
        const text = [
            `======= STRUK - ${settings?.store_name ?? 'POS'} =======`,
            `No Faktur : ${order.invoice?.invoice_number ?? '-'}`,
            `No Pesanan: ${order.order_number}`,
            `Meja      : ${order.table_number ?? '-'}`,
            '---------------------------',
            ...order.items.map((it) => `${it.qty} x ${it.product?.name}`),
            '---------------------------',
            `Total     : ${formatIDR(order.total_amount)}`,
            new Date().toLocaleString('id-ID'),
            settings?.receipt_footer ?? 'Terima kasih!',
        ].join('\n');
        const win = window.open('', '_blank');
        win.document.write(`<pre style="font:12px monospace;padding:16px">${text}</pre>`);
        win.document.close();
        win.print();
    };

    const header = {
        navLabel: 'Pesanan',
        showCatalog: true,
        mode,
        onModeChange: setMode,
        query,
        onQueryChange: setQuery,
        navItems: [
            { key: 'orders', label: 'Pesanan', icon: NAV_ICONS.orders },
            { key: 'draft', label: 'Draft', icon: NAV_ICONS.draft, count: pending.length },
            { key: 'history', label: 'Riwayat', icon: NAV_ICONS.history },
        ],
        activeNav: tab === 'cart' ? 'orders' : tab,
        onNavChange: setTab,
        onCheckOrders: () => setTab((t) => (t === 'cart' ? 'orders' : 'cart')),
        orderCount: cart.reduce((sum, line) => sum + line.qty, 0),
    };

    const checkoutSidebar = (
        <div>
            <CheckoutPanel
                cart={cart}
                table={table}
                setTable={setTable}
                enableTable={enableTable}
                tableNumbers={tableNumbers}
                discountType={discountType}
                setDiscountType={setDiscountType}
                discountRaw={discountRaw}
                setDiscountRaw={setDiscountRaw}
                taxRate={taxRate}
                enablePpn={enablePpn}
                enablePrepay={enablePrepay}
                paymentMethod={paymentMethod}
                setPaymentMethod={setPaymentMethod}
                payMethods={payMethods}
                qrisId={settings?.qris_id}
                totals={totals}
                paidRaw={paidRaw}
                onPaidChange={(digits) => {
                    setPaidTouched(true);
                    setPaidRaw(digits);
                }}
                paid={paid}
                change={change}
                canSubmit={canSubmit}
                canDraft={canDraft}
                submitting={submitting}
                onSubmit={() => submitOrder('pay_now')}
                onDraft={() => submitOrder('pay_later')}
            />
        </div>
    );

    return (
        <Layout header={header}>
            {tab === 'orders' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2">
                        <ProductCatalog
                            products={products}
                            onAdd={addToCart}
                            mode={mode}
                            onModeChange={setMode}
                            query={query}
                            onQueryChange={setQuery}
                            showStock={showStock}
                            showFavorites={showFavorites}
                            hideToolbar
                        />
                    </div>

                    {checkoutSidebar}
                </div>
            )}

            {tab === 'cart' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2">
                        <div className="card">
                            <div className="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                                <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                                    <ShoppingCart size={16} /> Cek Pesanan
                                </h3>
                                <span className="badge badge-pending">{cart.length} item</span>
                            </div>

                            {cart.length === 0 ? (
                                <p className="text-muted text-sm text-center py-10">Keranjang kosong. Tambahkan produk dari halaman Pesanan.</p>
                            ) : (
                                <>
                                    <div className="border border-gray-100 rounded-xl overflow-hidden mb-4 bg-white">
                                        <table className="w-full">
                                            <thead className="bg-gray-100">
                                                <tr>
                                                    <th className="table-head">Produk</th>
                                                    <th className="table-head text-right">Harga</th>
                                                    <th className="table-head text-center">Qty</th>
                                                    <th className="table-head text-right">Subtotal</th>
                                                    <th className="table-head text-center w-14"></th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-50">
                                                {cart.map((line) => (
                                                    <tr key={line.product_id}>
                                                        <td className="table-cell font-semibold">{line.name}</td>
                                                        <td className="table-cell text-right">{formatIDR(line.price)}</td>
                                                        <td className="table-cell text-center">
                                                            <div className="inline-flex items-center gap-1">
                                                                <button onClick={() => updateQty(line.product_id, -1)} className="btn-icon bg-white w-7 h-7 border border-gray-200"><Minus size={14} /></button>
                                                                <span className="w-6 text-center font-semibold text-sm">{line.qty}</span>
                                                                <button onClick={() => updateQty(line.product_id, 1)} className="btn-icon bg-orange-100 text-orange-700 w-7 h-7"><Plus size={14} /></button>
                                                            </div>
                                                        </td>
                                                        <td className="table-cell text-right font-semibold text-orange-600">{formatIDR(line.price * line.qty)}</td>
                                                        <td className="table-cell text-center">
                                                            <button onClick={() => removeLine(line.product_id)} className="text-red-500 hover:text-red-700 p-1"><Trash2 size={15} /></button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="bg-gray-100 border-t border-gray-100">
                                                <tr>
                                                    <td className="table-cell font-bold" colSpan={3}>Subtotal</td>
                                                    <td className="table-cell font-bold text-right">{formatIDR(totals.subtotal)}</td>
                                                    <td className="table-cell"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div className="flex justify-end">
                                        <button className="btn btn-ghost" onClick={() => setTab('orders')}>
                                            <ChevronLeft size={15} /> Kembali ke Pesanan
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    {checkoutSidebar}
                </div>
            )}

            {tab === 'draft' && (
                <div>
                    {pending.length === 0 ? (
                        <div className="card text-muted text-center py-10">Tidak ada draft menunggu pelunasan.</div>
                    ) : (
                        <div className="border border-gray-100 rounded-xl overflow-hidden bg-white">
                            <table className="w-full">
                                <thead className="bg-gray-100">
                                    <tr>
                                        <th className="table-head">Faktur</th>
                                        <th className="table-head">Pesanan</th>
                                        <th className="table-head text-right">Total</th>
                                        <th className="table-head text-right">Terbayar</th>
                                        <th className="table-head text-right">Sisa</th>
                                        <th className="table-head text-center">Status</th>
                                        <th className="table-head text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {pending.map((invoice) => {
                                        const received = (invoice.receipts ?? []).reduce((sum, r) => sum + Number(r.gross_amount), 0);
                                        const remaining = Number(invoice.total_amount) - received;
                                        const method = selected[invoice.order.id] ?? payMethods[0]?.code ?? 'cash';
                                        return (
                                            <tr key={invoice.id}>
                                                <td className="table-cell">
                                                    <div className="font-semibold text-xs">{invoice.invoice_number}</div>
                                                    <div className="text-xs text-muted">{invoice.order?.order_number}</div>
                                                </td>
                                                <td className="table-cell">
                                                    <div className="text-xs text-muted">Meja {invoice.order?.table_number ?? '-'}</div>
                                                    <div className="text-xs text-muted max-w-[16rem] truncate">
                                                        {invoice.order?.items?.map((i) => `${i.qty}× ${i.product?.name}`).join(', ')}
                                                    </div>
                                                </td>
                                                <td className="table-cell text-right font-semibold whitespace-nowrap">{formatIDR(invoice.total_amount)}</td>
                                                <td className="table-cell text-right text-emerald-700 whitespace-nowrap">{formatIDR(received)}</td>
                                                <td className="table-cell text-right font-bold text-orange-600 whitespace-nowrap">{formatIDR(remaining)}</td>
                                                <td className="table-cell text-center">
                                                    <span className={`badge ${received > 0 ? 'badge-cooking' : 'badge-unpaid'}`}>
                                                        {received > 0 ? 'Bayar Sebagian' : 'Belum Bayar'}
                                                    </span>
                                                </td>
                                                <td className="table-cell">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <select
                                                            value={method}
                                                            onChange={(e) => setSelected((s) => ({ ...s, [invoice.order.id]: e.target.value }))}
                                                            className="select !w-auto !py-1.5 text-xs"
                                                        >
                                                            {payMethods.map((m) => (
                                                                <option key={m.code} value={m.code}>{m.name}</option>
                                                            ))}
                                                        </select>
                                                        <button onClick={() => settle(invoice)} className="btn btn-success !py-1.5 whitespace-nowrap">
                                                            <CheckCircle2 size={14} /> Terima
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {tab === 'history' && (
                <div>
                    {today.length === 0 ? (
                        <div className="card text-muted text-center py-10">Belum ada transaksi hari ini.</div>
                    ) : (
                        <div className="border border-gray-100 rounded-xl overflow-hidden bg-white">
                            <table className="w-full">
                                <thead className="bg-gray-100">
                                    <tr>
                                        <th className="table-head">Faktur</th>
                                        <th className="table-head">Metode</th>
                                        <th className="table-head text-right">Diterima</th>
                                        <th className="table-head"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {today.map((receipt) => (
                                        <tr key={receipt.id}>
                                            <td className="table-cell">
                                                <div className="font-semibold text-xs">{receipt.invoice?.invoice_number}</div>
                                                <div className="text-xs text-muted">{receipt.invoice?.order?.order_number}</div>
                                            </td>
                                            <td className="table-cell"><PayMethodBadge method={receipt.payment_method} /></td>
                                            <td className="table-cell font-semibold text-right">{formatIDR(receipt.net_amount)}</td>
                                            <td className="table-cell">
                                                <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => printReceipt(receipt.invoice?.order)} title="Cetak Struk">
                                                    <Printer size={14} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="bg-gray-100 border-t border-gray-100">
                                    <tr>
                                        <td className="table-cell font-bold" colSpan={2}>Total Net (Kasir)</td>
                                        <td className="table-cell font-bold text-right text-emerald-700">{formatIDR(totalToday)}</td>
                                        <td className="table-cell"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </div>
            )}

            </Layout>
    );
}

function MethodChip({ method, active, onClick }) {
    const Icons = { cash: Banknote, bank: Landmark, qris: QrCode };
    const Icon = Icons[method.code] ?? CreditCard;
    return (
        <button
            onClick={onClick}
            className={`whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer ${
                active ? 'bg-orange-600 text-white' : 'bg-gray-100 text-muted hover:bg-gray-200'
            }`}
        >
            <Icon size={14} /> {method.name}
        </button>
    );
}

function CheckoutPanel({
    cart, updateQty, removeLine,
    table, setTable, enableTable, tableNumbers,
    discountType, setDiscountType, discountRaw, setDiscountRaw,
    enablePpn, taxRate, enablePrepay,
    paymentMethod, setPaymentMethod, payMethods, qrisId,
    totals, paidRaw, onPaidChange, paid, change,
    canSubmit, canDraft, submitting, onSubmit, onDraft,
}) {
    const totalAmount = totals.total;
    let status = null;
    if (cart.length > 0 && totalAmount > 0) {
        if (paid >= totalAmount) {
            status = { text: 'Lunas', cls: 'badge-paid' };
        } else if (paid > 0) {
            status = enablePrepay
                ? { text: 'Belum Lunas', cls: 'badge-unpaid' }
                : { text: `Kurang ${formatIDR(totalAmount - paid)}`, cls: 'badge-unpaid' };
        } else {
            status = { text: 'Belum Bayar', cls: 'badge-unpaid' };
        }
    }

    return (
        <div className="card sticky top-20">
            <div className="space-y-5">
                {enableTable && (
                    <div className="flex items-center gap-3">
                        <span className="label w-28 shrink-0 !mb-0">Meja</span>
                        <div className="flex-1">
                            <div className="relative">
                                <select className="select appearance-none pr-8" value={table} onChange={(e) => setTable(e.target.value)}>
                                    <option value="">Pilih nomor meja...</option>
                                    {tableNumbers.map((num) => (
                                        <option key={num} value={`Meja ${num}`}>Meja {num}</option>
                                    ))}
                                </select>
                                <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                            </div>
                            {cart.length > 0 && table === '' && (
                                <p className="text-[11px] text-red-500 mt-1 flex items-center gap-1"><AlertCircle size={11} /> Nomor meja wajib dipilih.</p>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <span className="label w-28 shrink-0 !mb-0">Diskon</span>
                    <div className="flex-1 flex items-center gap-2">
                        <button
                            onClick={() => setDiscountType((t) => (t === 'percent' ? 'amount' : 'percent'))}
                            title={discountType === 'percent' ? 'Diskon persen (%) — klik untuk Rupiah' : 'Diskon nominal (Rp) — klik untuk persen'}
                            className="w-10 py-2.5 rounded-lg text-xs font-bold shrink-0 cursor-pointer bg-orange-600 text-white hover:bg-orange-700 transition-colors"
                        >
                            {discountType === 'percent' ? '%' : 'Rp'}
                        </button>
                        {discountType === 'percent' ? (
                            <div className="relative flex-1">
                                <input type="number" min="0" max="100" className="input pr-10" value={discountRaw} onChange={(e) => setDiscountRaw(e.target.value)} placeholder="0" />
                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted">%</span>
                            </div>
                        ) : (
                            <div className="flex-1">
                                <CurrencyInput value={discountRaw} onChange={setDiscountRaw} placeholder="0" />
                            </div>
                        )}
                    </div>
                </div>

                {enablePpn && (
                    <div className="flex items-center gap-3">
                        <span className="label w-28 shrink-0 !mb-0">PPN</span>
                        <div className="flex-1 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 text-right text-sm font-bold">
                            {taxRate}%
                        </div>
                    </div>
                )}

                <div>
                    <span className="label">Metode Pembayaran</span>
                    <div className="flex flex-wrap gap-2">
                        {payMethods.map((m) => (
                            <MethodChip key={m.code} method={m} active={paymentMethod === m.code} onClick={() => setPaymentMethod(m.code)} />
                        ))}
                    </div>
                </div>

                {paymentMethod === 'qris' && qrisId && (
                    <div className="bg-purple-50 border border-purple-100 rounded-lg p-3">
                        <div className="flex items-center gap-2 mb-2 text-purple-700 font-bold text-sm">
                            <QrCode size={15} /> QRIS — Scan untuk Bayar
                        </div>
                        <QrisQrCode value={qrisId} />
                        <div className="text-[11px] text-purple-500 mt-2 text-center">{qrisId}</div>
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <span className="label w-28 shrink-0 !mb-0">Nominal</span>
                    <div className="flex-1 flex items-center gap-2">
                        <div className="flex-1 min-w-0">
                            <CurrencyInput value={paidRaw} onChange={onPaidChange} />
                        </div>
                        {status && <span className={`badge shrink-0 ${status.cls}`}>{status.text}</span>}
                    </div>
                </div>

                {change > 0 && (
                    <p className="text-[11px] text-muted text-right -mt-2">Kembalian {formatIDR(change)}</p>
                )}
                {paid > 0 && paid < totalAmount && (
                    <p className="text-[11px] text-right -mt-2">
                        {enablePrepay ? (
                            <span className="text-muted">Bayar sebagian diperbolehkan — status Belum Lunas.</span>
                        ) : (
                            <span className="text-red-500">Nominal harus melebihi total tagihan.</span>
                        )}
                    </p>
                )}

                <div className="flex items-center justify-between border-t border-gray-100 pt-3">
                    <span className="font-bold text-sm">Total</span>
                    <span className="font-bold text-lg text-orange-600">{formatIDR(totals.total)}</span>
                </div>

                <div className="grid grid-cols-2 gap-2 pt-1">
                    <button className="btn btn-ghost justify-center" disabled={!canDraft} onClick={onDraft}>
                        <Save size={16} /> Draft
                    </button>
                    <button className="btn btn-success justify-center" disabled={!canSubmit} onClick={onSubmit}>
                        <Send size={16} /> {submitting ? 'Memproses...' : 'Simpan / Bayar'}
                    </button>
                </div>
            </div>
        </div>
    );
}