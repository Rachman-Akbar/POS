import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Banknote, CreditCard, Landmark, Printer, ReceiptText, CheckCircle2, TrendingUp,
    Minus, Plus, Trash2, ShoppingCart, Send, QrCode, Percent, Timer, UtensilsCrossed, ScrollText,
} from 'lucide-react';
import Layout from '../components/Layout';
import Tabs from '../components/Tabs';
import ProductCatalog from '../components/ProductCatalog';
import QrisQrCode from '../components/QrisQrCode';
import { PayMethodBadge } from '../components/badges';
import { api, formatIDR } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifySuccess, notifyError } from '../utils/alerts';

export default function CashierDashboard() {
    const queryClient = useQueryClient();

    const [tab, setTab] = useState('orders');
    const [cart, setCart] = useState([]);
    const [tableNumber, setTableNumber] = useState('');
    const [paymentType, setPaymentType] = useState('pay_now');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [discount, setDiscount] = useState(0);
    const [taxRate, setTaxRate] = useState(11);
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

    useEffect(() => {
        if (settings) {
            setTaxRate(Number(settings.ppn_rate ?? 11));
            if (settings.payment_methods?.length && !settings.payment_methods.some((m) => m.code === paymentMethod)) {
                setPaymentMethod(settings.payment_methods[0].code);
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
    const methodInfo = methods.find((m) => m.code === paymentMethod);
    const payMethods = methods.length > 0 ? methods : [{ code: 'cash', name: 'Tunai', mdr_rate: 0 }];

    const totals = useMemo(() => {
        const subtotal = cart.reduce((sum, l) => sum + l.price * l.qty, 0);
        const disc = Math.min(Number(discount) || 0, subtotal);
        const base = subtotal - disc;
        const tax = base * (Number(taxRate) || 0) / 100;
        const total = base + tax;
        return { subtotal, discount: disc, tax, total };
    }, [cart, discount, taxRate]);

    const totalToday = today.reduce((sum, r) => sum + Number(r.net_amount), 0);

    const addToCart = (product) => {
        setCart((prev) => {
            const existing = prev.find((l) => l.product_id === product.id);
            if (existing) {
                return prev.map((l) => (l.product_id === product.id ? { ...l, qty: l.qty + 1 } : l));
            }
            return [...prev, { product_id: product.id, name: product.name, price: Number(product.price), qty: 1 }];
        });
    };

    const updateQty = (id, delta) => {
        setCart((prev) =>
            prev
                .map((l) => (l.product_id === id ? { ...l, qty: Math.max(0, l.qty + delta) } : l))
                .filter((l) => l.qty > 0),
        );
    };

    const removeLine = (id) => setCart((prev) => prev.filter((l) => l.product_id !== id));

    const submitOrder = async () => {
        if (cart.length === 0) return;
        setSubmitting(true);
        try {
            await api.post('/orders', {
                table_number: tableNumber,
                payment_type: paymentType,
                discount: totals.discount,
                tax_rate: Number(taxRate) || 0,
                payment_method: paymentType === 'pay_now' ? paymentMethod : undefined,
                items: cart.map(({ product_id, qty }) => ({ product_id, qty })),
            });
            setCart([]);
            setDiscount(0);
            setTableNumber('');
            notifySuccess('Transaksi berhasil diproses.');
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
        const method = selected[invoice.order.id] ?? 'cash';
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

    return (
        <Layout
            title="Kasir"
            subtitle="Katalog produk & input transaksi"
            right={
                <span className="badge badge-paid bg-emerald-50">
                    <TrendingUp size={14} /> Hari ini: {formatIDR(totalToday)}
                </span>
            }
        >
            <Tabs
                tabs={[
                    { key: 'orders', label: 'Pesanan', icon: UtensilsCrossed },
                    { key: 'pending', label: 'Faktur Gantung', icon: ReceiptText },
                    { key: 'history', label: 'Riwayat Transaksi', icon: ScrollText },
                ]}
                active={tab}
                onChange={setTab}
            />

            {tab === 'orders' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    {/* LEFT: product catalog */}
                    <div className="xl:col-span-2">
                        <ProductCatalog products={products} onAdd={addToCart} />
                    </div>

                    {/* RIGHT: billing sidebar */}
                    <div>
                        <div className="card sticky top-6">
                            <h3 className="font-bold mb-4 flex items-center gap-2">
                                <ShoppingCart size={18} /> Transaksi {cart.length > 0 && `(${cart.length} item)`}
                            </h3>

                            <div className="mb-4">
                                <label className="label">No. Meja</label>
                                <input
                                    className="input"
                                    value={tableNumber}
                                    onChange={(e) => setTableNumber(e.target.value)}
                                    placeholder="mis. Meja 5"
                                />
                            </div>

                            {cart.length === 0 ? (
                                <p className="text-muted text-sm py-6 text-center">Pilih produk dari daftar di kiri.</p>
                            ) : (
                                <div className="space-y-2 max-h-56 overflow-y-auto pr-1 mb-3">
                                    {cart.map((line) => (
                                        <div key={line.product_id} className="flex items-center gap-2 bg-gray-50 rounded-xl p-2">
                                            <div className="flex-1 min-w-0">
                                                <div className="text-sm font-semibold truncate">{line.name}</div>
                                                <div className="text-xs text-muted">{formatIDR(line.price)}</div>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <button onClick={() => updateQty(line.product_id, -1)} className="btn-icon bg-white w-7 h-7 border border-gray-200">
                                                    <Minus size={14} />
                                                </button>
                                                <span className="w-6 text-center font-semibold text-sm">{line.qty}</span>
                                                <button onClick={() => updateQty(line.product_id, 1)} className="btn-icon bg-orange-100 text-orange-700 w-7 h-7">
                                                    <Plus size={14} />
                                                </button>
                                            </div>
                                            <button onClick={() => removeLine(line.product_id)} className="text-red-500 hover:text-red-700 p-1">
                                                <Trash2 size={15} />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div className="mb-4">
                                <label className="label flex items-center gap-1"><Percent size={13} /> Diskon (Rp)</label>
                                <input
                                    type="number" min="0"
                                    className="input"
                                    value={discount}
                                    onChange={(e) => setDiscount(Number(e.target.value))}
                                    placeholder="0"
                                />
                            </div>

                            <div className="mb-4">
                                <label className="label">PPN (%)</label>
                                <input type="number" min="0" className="input" value={taxRate} onChange={(e) => setTaxRate(Number(e.target.value))} />
                            </div>

                            <div className="space-y-1.5 text-sm border-t border-gray-200 pt-3 mb-4">
                                <div className="flex justify-between"><span className="text-muted">Subtotal</span><span>{formatIDR(totals.subtotal)}</span></div>
                                <div className="flex justify-between"><span className="text-muted">Diskon</span><span className="text-red-500">-{formatIDR(totals.discount)}</span></div>
                                <div className="flex justify-between"><span className="text-muted">PPN</span><span>{formatIDR(totals.tax)}</span></div>
                                <div className="flex justify-between items-center font-bold text-lg pt-2 border-t border-gray-200">
                                    <span>Total</span>
                                    <span className="text-orange-600">{formatIDR(totals.total)}</span>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2 mb-4">
                                <button
                                    onClick={() => setPaymentType('pay_now')}
                                    className={`btn border ${paymentType === 'pay_now' ? 'bg-emerald-600 text-white border-emerald-600' : 'border-gray-300 text-gray-600'}`}
                                >
                                    <Timer size={15} /> Bayar Dulu
                                </button>
                                <button
                                    onClick={() => setPaymentType('pay_later')}
                                    className={`btn border ${paymentType === 'pay_later' ? 'bg-amber-500 text-white border-amber-500' : 'border-gray-300 text-gray-600'}`}
                                >
                                    <ReceiptText size={15} /> Bayar Nanti
                                </button>
                            </div>

                            {paymentType === 'pay_now' && (
                                <div className="mb-3">
                                    <label className="label">Metode Pembayaran</label>
                                    <div className="flex flex-wrap gap-2">
                                        {payMethods.map((m) => (
                                            <button
                                                key={m.code}
                                                onClick={() => setPaymentMethod(m.code)}
                                                className={`btn border text-xs ${paymentMethod === m.code ? 'bg-orange-600 text-white border-orange-600' : 'border-gray-300 text-gray-600'}`}
                                            >
                                                {m.code === 'cash' ? <Banknote size={14} /> : m.code === 'bank' ? <Landmark size={14} /> : <CreditCard size={14} />}
                                                {m.name} <span className="opacity-70">({(Number(m.mdr_rate) * 100).toLocaleString('id-ID')}%)</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {paymentType === 'pay_now' && paymentMethod === 'qris' && settings?.qris_id && (
                                <div className="mb-4 bg-purple-50 border border-purple-200 rounded-xl p-3">
                                    <div className="flex items-center gap-2 mb-2 text-purple-700 font-bold text-sm">
                                        <QrCode size={15} /> QRIS — Scan untuk Bayar
                                    </div>
                                    <QrisQrCode value={settings.qris_id} />
                                    <div className="text-[11px] text-purple-500 mt-2 text-center">
                                        {settings.qris_id} · MDR {((methodInfo?.mdr_rate ?? 0) * 100).toLocaleString('id-ID')}%
                                    </div>
                                </div>
                            )}

                            <button className="btn btn-success w-full justify-center" disabled={cart.length === 0 || submitting} onClick={submitOrder}>
                                <Send size={16} /> {submitting ? 'Memproses...' : paymentType === 'pay_now' ? 'Bayar & Kirim ke Dapur' : 'Simpan & Kirim ke Dapur'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'pending' && (
                <div>
                    {pending.length === 0 ? (
                        <div className="card text-muted text-center py-10">Tidak ada faktur menunggu pelunasan.</div>
                    ) : (
                        <div className="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                            {pending.map((invoice) => (
                                <div key={invoice.id} className="card">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <div className="font-bold">{invoice.invoice_number}</div>
                                            <div className="text-xs text-muted">{invoice.order?.order_number} · Meja {invoice.order?.table_number ?? '-'}</div>
                                        </div>
                                        <span className="badge badge-unpaid">Belum Bayar</span>
                                    </div>

                                    <div className="my-3 bg-gray-50 rounded-xl p-3">
                                        {invoice.order?.items?.map((item) => (
                                            <div key={item.id} className="flex justify-between text-sm py-0.5">
                                                <span>{item.qty} × {item.product?.name}</span>
                                                <span className="text-muted">{formatIDR(Number(item.price) * item.qty)}</span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="flex justify-between items-center mb-3">
                                        <span className="font-bold">Total Tagihan</span>
                                        <span className="font-bold text-orange-600 text-lg">{formatIDR(invoice.total_amount)}</span>
                                    </div>

                                    <div className="flex flex-wrap gap-2 mb-3">
                                        {payMethods.map((m) => (
                                            <button
                                                key={m.code}
                                                onClick={() => setSelected((s) => ({ ...s, [invoice.order.id]: m.code }))}
                                                className={`btn border text-xs ${selected[invoice.order.id] === m.code ? 'bg-orange-600 text-white border-orange-600' : 'border-gray-300 text-gray-600'}`}
                                            >
                                                {m.code === 'cash' ? <Banknote size={14} /> : m.code === 'bank' ? <Landmark size={14} /> : <CreditCard size={14} />}
                                                {m.name}
                                            </button>
                                        ))}
                                    </div>

                                    <button onClick={() => settle(invoice)} className="btn btn-success w-full justify-center">
                                        <CheckCircle2 size={16} /> Terima Pembayaran
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {tab === 'history' && (
                <div>
                    {today.length === 0 ? (
                        <div className="card text-muted text-center py-10">Belum ada transaksi hari ini.</div>
                    ) : (
                        <div className="card !p-0 overflow-hidden">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="table-head">Faktur</th>
                                        <th className="table-head">Metode</th>
                                        <th className="table-head">MDR</th>
                                        <th className="table-head text-right">Diterima</th>
                                        <th className="table-head"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {today.map((receipt) => (
                                        <tr key={receipt.id}>
                                            <td className="table-cell">
                                                <div className="font-semibold text-xs">{receipt.invoice?.invoice_number}</div>
                                                <div className="text-xs text-muted">{receipt.invoice?.order?.order_number}</div>
                                            </td>
                                            <td className="table-cell"><PayMethodBadge method={receipt.payment_method} /></td>
                                            <td className="table-cell text-xs text-muted">{formatIDR(receipt.mdr_fee)}</td>
                                            <td className="table-cell font-semibold text-right">{formatIDR(receipt.net_amount)}</td>
                                            <td className="table-cell">
                                                <button className="btn-secondary btn !px-2 !py-1.5" onClick={() => printReceipt(receipt.invoice?.order)} title="Cetak Struk">
                                                    <Printer size={14} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="bg-gray-50 border-t border-gray-200">
                                    <tr>
                                        <td className="table-cell font-bold" colSpan={3}>Total Net (Kasir)</td>
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