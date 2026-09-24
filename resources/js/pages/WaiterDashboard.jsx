import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    CheckCircle2, Minus, Plus, ShoppingCart, Trash2, Send,
    Clock, Bike, Percent, ReceiptText,
} from 'lucide-react';
import Layout from '../components/Layout';
import ProductCatalog from '../components/ProductCatalog';
import { StatusBadge, PaymentBadge } from '../components/badges';
import { api, formatIDR } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifySuccess, notifyError } from '../utils/alerts';

export default function WaiterDashboard() {
    const [tab, setTab] = useState('antar');
    const [cart, setCart] = useState([]);
    const [tableNumber, setTableNumber] = useState('');
    const [discount, setDiscount] = useState(0);
    const [taxRate, setTaxRate] = useState(11);
    const [submitting, setSubmitting] = useState(false);
    const queryClient = useQueryClient();

    const { data: products = [], isLoading } = useQuery({
        queryKey: ['products'],
        queryFn: async () => (await api.get('/products')).data.data,
    });

    const { data: settings } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => (await api.get('/settings')).data.data,
    });

    const { data: ready = [] } = useQuery({
        queryKey: ['waiter-ready'],
        queryFn: async () => (await api.get('/orders', { params: { ready: true } })).data.data,
        refetchInterval: 10_000,
    });

    useEffect(() => {
        if (settings) {
            setTaxRate(Number(settings.ppn_rate ?? 11));
        }
    }, [settings]);

    useEffect(() => {
        const channel = listenToOrders({
            onOrderCreated: () => queryClient.invalidateQueries({ queryKey: ['waiter-ready'] }),
            onItemUpdated: () => queryClient.invalidateQueries({ queryKey: ['waiter-ready'] }),
        });
        return () => {
            channel?.stopListening?.('order.created');
            channel?.stopListening?.('item.status.updated');
        };
    }, [queryClient]);

    const totals = useMemo(() => {
        const subtotal = cart.reduce((sum, l) => sum + l.price * l.qty, 0);
        const disc = Math.min(Number(discount) || 0, subtotal);
        const base = subtotal - disc;
        const tax = base * (Number(taxRate) || 0) / 100;
        return { subtotal, discount: disc, tax, total: base + tax };
    }, [cart, discount, taxRate]);

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
                payment_type: 'pay_later',
                discount: totals.discount,
                tax_rate: Number(taxRate) || 0,
                items: cart.map(({ product_id, qty }) => ({ product_id, qty })),
            });
            setCart([]);
            setTableNumber('');
            setDiscount(0);
            notifySuccess('Pesanan dikirim ke dapur — pembayaran ditagih nanti (pay later).');
            queryClient.invalidateQueries({ queryKey: ['products'] });
        } catch (err) {
            notifyError('Pesanan gagal', err.response?.data?.message ?? 'Gagal membuat pesanan.');
        } finally {
            setSubmitting(false);
        }
    };

    const deliver = async (order) => {
        try {
            await api.post(`/orders/${order.id}/complete`);
            notifySuccess(`${order.order_number} telah diantar ke pelanggan.`);
            queryClient.invalidateQueries({ queryKey: ['waiter-ready'] });
        } catch (err) {
            notifyError('Gagal', err.response?.data?.message ?? 'Gagal menyelesaikan pesanan.');
        }
    };

    const header = {
        navLabel: 'Waiter',
        navItems: [
            { key: 'antar', label: 'Antar', icon: Bike, count: ready.length },
            { key: 'pesanan', label: 'Pesanan', icon: ShoppingCart },
        ],
        activeNav: tab,
        onNavChange: setTab,
    };

    return (
        <Layout header={header}>
            {tab === 'antar' && (
                <div>
                    <div className="flex items-center gap-2 mb-4">
                        <Clock size={15} className="text-muted" />
                        <span className="font-bold capitalize text-sm">Siap Antar (dari dapur selesai)</span>
                        <span className="badge badge-done ml-auto">{ready.length} pesanan</span>
                    </div>

                    {ready.length === 0 ? (
                        <div className="card text-muted text-center py-14">Belum ada pesanan yang selesai dari dapur.</div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
                            {ready.map((order) => (
                                <div key={order.id} className="card">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <span className="font-bold">{order.order_number}</span>
                                            <span className="badge badge-pending ml-2">Meja {order.table_number ?? '-'}</span>
                                        </div>
                                        <div className="flex gap-1">
                                            <StatusBadge status={order.status} />
                                        </div>
                                    </div>

                                    <div className="my-3 bg-gray-50 rounded-xl p-3 space-y-1">
                                        {order.items.map((item) => (
                                            <div key={item.id} className="flex justify-between text-sm">
                                                <span>{item.qty} × {item.product?.name}</span>
                                                <span className="text-muted">{formatIDR(Number(item.price) * item.qty)}</span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="flex justify-between items-center text-sm mb-3">
                                        <PaymentBadge status={order.payment_status} />
                                        <span className="font-bold">{formatIDR(order.total_amount)}</span>
                                    </div>

                                    <button onClick={() => deliver(order)} className="btn btn-success w-full justify-center">
                                        <CheckCircle2 size={16} /> Antar ke Pelanggan
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {tab === 'pesanan' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2">
                        <ProductCatalog products={products} onAdd={addToCart} />
                    </div>

                    <div>
                        <div className="card sticky top-20">
                            <h3 className="font-bold mb-3 flex items-center gap-2">
                                <ShoppingCart size={18} /> Pesanan {cart.length > 0 && `(${cart.length} item)`}
                            </h3>

                            <div className="mb-3">
                                <label className="label">No. Meja</label>
                                <input className="input" value={tableNumber} onChange={(e) => setTableNumber(e.target.value)} placeholder="mis. Meja 5" />
                            </div>

                            {cart.length === 0 ? (
                                <p className="text-muted text-sm py-6 text-center">Belum ada item dipilih.</p>
                            ) : (
                                <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
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

                            <div className="mb-3">
                                <label className="label flex items-center gap-1"><Percent size={13} /> Diskon (Rp)</label>
                                <input type="number" min="0" className="input" value={discount} onChange={(e) => setDiscount(Number(e.target.value))} placeholder="0" />
                            </div>

                            <div className="mb-3">
                                <label className="label">PPN (%)</label>
                                <input type="number" min="0" className="input" value={taxRate} onChange={(e) => setTaxRate(Number(e.target.value))} />
                            </div>

                            <div className="space-y-1.5 text-sm border-t border-gray-200 pt-3 mb-3">
                                <div className="flex justify-between"><span className="text-muted">Subtotal</span><span>{formatIDR(totals.subtotal)}</span></div>
                                <div className="flex justify-between"><span className="text-muted">Diskon</span><span className="text-red-500">-{formatIDR(totals.discount)}</span></div>
                                <div className="flex justify-between"><span className="text-muted">PPN</span><span>{formatIDR(totals.tax)}</span></div>
                                <div className="flex justify-between items-center font-bold text-lg pt-2 border-t border-gray-200">
                                    <span>Total</span>
                                    <span className="text-orange-600">{formatIDR(totals.total)}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-2">
                                <ReceiptText size={14} /> Status pembayaran: otomatis Bayar Nanti (served by kasir).
                            </div>

                            <button className="btn btn-primary w-full justify-center" disabled={cart.length === 0 || submitting} onClick={submitOrder}>
                                <Send size={16} /> {submitting ? 'Mengirim...' : 'Kirim ke Dapur'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </Layout>
    );
}