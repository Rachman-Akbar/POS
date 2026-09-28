import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Banknote, CreditCard, Landmark, Printer, ReceiptText, Check, CheckCircle2,
    Minus, Plus, Trash2, ShoppingCart, QrCode, UtensilsCrossed, ScrollText, AlertCircle,
    Save, ChevronLeft, ChevronRight, ImageOff,
} from 'lucide-react';
import Layout from '../components/Layout';
import ProductCatalog, { ALL_CATEGORIES, catalogSectionKeys } from '../components/ProductCatalog';
import QrisQrCode from '../components/QrisQrCode';
import CurrencyInput from '../components/CurrencyInput';
import Price, { PriceRow } from '../components/Price';
import SearchSelect from '../components/SearchSelect';
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
    const [category, setCategory] = useState(ALL_CATEGORIES);
    const [cart, setCart] = useState([]);
    const [table, setTable] = useState('');
    const [discountType, setDiscountType] = useState('percent');
    const [discountRaw, setDiscountRaw] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [payOpen, setPayOpen] = useState(false);
    const [paidRaw, setPaidRaw] = useState('');
    const [paidTouched, setPaidTouched] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [selected, setSelected] = useState({});
    const [collapsed, setCollapsed] = useState({});

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
    const taxRate = Number(settings?.ppn_rate ?? 11);
    const tableNumbers = settings?.table_numbers ?? Array.from({ length: 20 }, (_, i) => String(i + 1));

    const categories = useMemo(() => {
        const counts = new Map();
        products.forEach((product) => {
            const name = product.category ?? 'Lainnya';
            counts.set(name, (counts.get(name) ?? 0) + 1);
        });

        const order = settings?.category_order ?? [];
        const position = (name) => {
            const index = order.indexOf(name);
            return index === -1 ? Number.MAX_SAFE_INTEGER : index;
        };

        return [...counts.entries()]
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => position(a.name) - position(b.name) || a.name.localeCompare(b.name, 'id-ID'));
    }, [products, settings]);

    useEffect(() => {
        if (settings) {
            const methods = settings.payment_methods ?? [];
            if (methods.length) {
                setPaymentMethod((current) => methods.find((m) => m.code === current)?.code ?? methods[0].code);
            }
        }
    }, [settings]);

    const sectionKeys = useMemo(
        () => catalogSectionKeys({ products, query, category, showFavorites }),
        [products, query, category, showFavorites]
    );

    const allSectionsOpen = sectionKeys.length > 0 && sectionKeys.every((key) => !collapsed[key]);

    const toggleAllSections = () => setCollapsed(Object.fromEntries(sectionKeys.map((key) => [key, allSectionsOpen])));

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
    const isLunas = paid >= total && total > 0;
    const canDraft = cart.length > 0 && !submitting;
    const canSubmit = canDraft && (!enableTable || table !== '') && (enablePrepay ? paid > 0 : isLunas);

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
        setPayOpen(false);
    };

    /**
     * The account is no longer picked at the counter: Admin decides which
     * account of the chosen method receives the money.
     */
    const defaultAccountOf = (methodCode) => {
        const method = payMethods.find((m) => m.code === methodCode) ?? payMethods[0];
        const accounts = (method?.accounts ?? []).filter((a) => a.is_active !== false);
        return accounts.find((a) => a.is_default) ?? accounts[0] ?? null;
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
        const target = payMethods.find((m) => m.code === (selected[invoice.order.id] ?? payMethods[0]?.code ?? 'cash')) ?? payMethods[0] ?? { code: 'cash' };
        try {
            await api.post(`/payments/orders/${invoice.order.id}/settle`, { payment_method: target.code });
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
        allOpen: allSectionsOpen,
        onToggleAll: toggleAllSections,
        query,
        onQueryChange: setQuery,
        categories,
        category,
        onCategoryChange: setCategory,
        favoritesCount: products.filter((product) => product.is_favorite).length,
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
        <CheckoutPanel
            cart={cart}
            products={products}
            updateQty={updateQty}
            removeLine={removeLine}
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
            onPaymentMethodChange={setPaymentMethod}
            payMethods={payMethods}
            defaultAccount={defaultAccountOf(paymentMethod)}
            qrisId={settings?.qris_id}
            totals={totals}
            paidRaw={paidRaw}
            onPaidChange={(digits) => {
                setPaidTouched(true);
                setPaidRaw(digits);
            }}
            paid={paid}
            change={change}
            payOpen={payOpen}
            onPayToggle={() => setPayOpen((v) => !v)}
            canSubmit={canSubmit}
            canDraft={canDraft}
            submitting={submitting}
            onClear={resetCheckout}
            onSubmit={() => submitOrder('pay_now')}
            onSaveDraft={() => submitOrder('pay_later')}
        />
    );

    return (
        <Layout header={header}>
            {tab === 'orders' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div className="xl:col-span-2">
                        <ProductCatalog
                            products={products}
                            onAdd={addToCart}
                            mode={mode}
                            onModeChange={setMode}
                            query={query}
                            onQueryChange={setQuery}
                            categories={categories}
                            category={category}
                            onCategoryChange={setCategory}
                            showStock={showStock}
                            showFavorites={showFavorites}
                            hideToolbar
                            collapsed={collapsed}
                            onCollapsedChange={setCollapsed}
                        />
                    </div>

                    {checkoutSidebar}
                </div>
            )}

            {tab === 'cart' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2">
                        <div className="card">
                            <div className="flex items-center justify-between pb-3 mb-4">
                                <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                                    <ShoppingCart size={16} /> Cek Pesanan
                                </h3>
                                <span className="badge badge-pending">{cart.length} item</span>
                            </div>

                            {cart.length === 0 ? (
                                <p className="text-muted text-sm text-center py-10">Keranjang kosong. Tambahkan produk dari halaman Pesanan.</p>
                            ) : (
                                <>
                                    <CartList
                                        view={mode}
                                        cart={cart}
                                        products={products}
                                        updateQty={updateQty}
                                        removeLine={removeLine}
                                    />
                                    <div className="flex justify-end mt-4">
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
                        <div className="border border-line rounded-xl overflow-hidden bg-surface">
                            <table className="w-full">
                                <thead className="border-b border-line">
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
                                <tbody className="divide-y divide-line">
                                    {pending.map((invoice) => {
                                        const received = (invoice.receipts ?? []).reduce((sum, r) => sum + Number(r.gross_amount), 0);
                                        const remaining = Number(invoice.total_amount) - received;
                                        const method = selected[invoice.order.id] ?? payMethods[0]?.code ?? 'cash';
                                        const account = defaultAccountOf(method);
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
                                                <td className="table-cell text-right text-positive whitespace-nowrap">{formatIDR(received)}</td>
                                                <td className="table-cell text-right font-bold text-accent whitespace-nowrap">{formatIDR(remaining)}</td>
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
                                                            title="Metode pembayaran"
                                                        >
                                                            {payMethods.map((m) => (
                                                                <option key={m.code} value={m.code}>{m.name}</option>
                                                            ))}
                                                        </select>
                                                        {account && (
                                                            <span className="text-[11px] text-muted text-right max-w-[10rem] truncate" title="Rekening tujuan diatur di Admin">
                                                                {account.bank_name ? `${account.bank_name} — ${account.name}` : account.name}
                                                            </span>
                                                        )}
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
                        <div className="border border-line rounded-xl overflow-hidden bg-surface">
                            <table className="w-full">
                                <thead className="border-b border-line">
                                    <tr>
                                        <th className="table-head">Faktur</th>
                                        <th className="table-head">Metode</th>
                                        <th className="table-head text-right">Diterima</th>
                                        <th className="table-head"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
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
                                <tfoot className="bg-surface-3 border-t border-line">
                                    <tr>
                                        <td className="table-cell font-bold" colSpan={2}>Total Net (Kasir)</td>
                                        <td className="table-cell font-bold text-right text-positive">{formatIDR(totalToday)}</td>
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

function QtyStepper({ qty, onChange, size = 'sm' }) {
    const box = size === 'sm' ? 'w-7 h-7' : 'w-9 h-9';
    return (
        <div className="inline-flex items-center gap-1">
            <button onClick={() => onChange(-1)} className={`btn-icon ${box} bg-surface-2`} title="Kurangi">
                <Minus size={14} />
            </button>
            <span className={`${size === 'sm' ? 'w-6 text-sm' : 'w-10 text-lg'} text-center font-bold`}>{qty}</span>
            <button onClick={() => onChange(1)} className={`btn-icon ${box} bg-accent-soft text-accent-ink hover:bg-accent hover:text-on-accent`} title="Tambah">
                <Plus size={14} />
            </button>
        </div>
    );
}

function LineThumb({ product }) {
    if (!product?.image) {
        return (
            <div className="w-10 h-10 rounded-lg bg-surface-2 flex items-center justify-center shrink-0">
                <ImageOff size={16} className="text-faint" />
            </div>
        );
    }
    return <img src={product.image} alt={product.name} loading="lazy" className="w-10 h-10 rounded-lg object-cover shrink-0" />;
}

/**
 * Cart lines rendered with the layout that matches the active view mode.
 */
function CartList({ view, cart, products = [], updateQty, removeLine }) {
    const productOf = (id) => products.find((p) => p.id === id);

    if (view === 'table') {
        return (
            <div className="border border-line rounded-xl overflow-hidden bg-surface">
                <table className="w-full">
                    <thead className="border-b border-line">
                        <tr>
                            <th className="table-head">Produk</th>
                            <th className="table-head text-right">Harga</th>
                            <th className="table-head text-center">Qty</th>
                            <th className="table-head text-right">Subtotal</th>
                            <th className="table-head text-center w-14"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {cart.map((line) => (
                            <tr key={line.product_id}>
                                <td className="table-cell font-semibold">{line.name}</td>
                                <td className="table-cell text-right">{formatIDR(line.price)}</td>
                                <td className="table-cell text-center">
                                    <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                                </td>
                                <td className="table-cell text-right font-semibold text-accent">{formatIDR(line.price * line.qty)}</td>
                                <td className="table-cell text-center">
                                    <button onClick={() => removeLine(line.product_id)} className="text-negative hover:text-red-700 dark:hover:text-red-300 p-1" title="Hapus item">
                                        <Trash2 size={15} />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    if (view === 'hero') {
        return <CartHero cart={cart} products={products} updateQty={updateQty} removeLine={removeLine} />;
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-3">
            {cart.map((line) => (
                <div key={line.product_id} className="bg-surface-2 rounded-xl p-3 flex items-center gap-3">
                    <LineThumb product={productOf(line.product_id)} />
                    <div className="flex-1 min-w-0">
                        <div className="font-semibold text-sm truncate">{line.name}</div>
                        <div className="text-xs text-muted">
                            {formatIDR(line.price)} · <span className="text-accent font-semibold">{formatIDR(line.price * line.qty)}</span>
                        </div>
                    </div>
                    <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                    <button onClick={() => removeLine(line.product_id)} className="text-negative hover:text-red-700 dark:hover:text-red-300 p-1" title="Hapus item">
                        <Trash2 size={15} />
                    </button>
                </div>
            ))}
        </div>
    );
}

function CartHero({ cart, products, updateQty, removeLine }) {
    const [active, setActive] = useState(0);
    const index = Math.min(active, cart.length - 1);
    const line = cart[index];
    const product = products.find((p) => p.id === line?.product_id);

    if (!line) return null;

    const go = (delta) => setActive((a) => Math.min(cart.length - 1, Math.max(0, a + delta)));

    return (
        <div className="rounded-xl overflow-hidden" style={{ height: 'calc(100dvh - 15rem)' }}>
            <div className="relative h-full flex flex-col">
                {product?.image ? (
                    <img src={product.image} alt={line.name} className="absolute inset-0 w-full h-full object-cover" />
                ) : (
                    <div className="absolute inset-0 bg-surface-2" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

                {cart.length > 1 && (
                    <>
                        <button
                            onClick={() => go(-1)}
                            disabled={index === 0}
                            className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 text-gray-700 flex items-center justify-center hover:bg-white disabled:opacity-40"
                        >
                            <ChevronLeft size={18} />
                        </button>
                        <button
                            onClick={() => go(1)}
                            disabled={index === cart.length - 1}
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 text-gray-700 flex items-center justify-center hover:bg-white disabled:opacity-40"
                        >
                            <ChevronRight size={18} />
                        </button>
                    </>
                )}

                <div className="relative mt-auto p-5 text-white flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <div className="text-[11px] uppercase tracking-wide text-white/70">
                            Item {index + 1} dari {cart.length}
                        </div>
                        <div className="text-xl font-bold leading-snug">{line.name}</div>
                        <div className="text-sm text-white/80 mt-0.5">{formatIDR(line.price)} / item</div>
                        <div className="text-2xl font-bold text-accent mt-1">{formatIDR(line.price * line.qty)}</div>
                    </div>

                    <div className="flex items-center gap-2">
                        <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} size="lg" />
                        <button
                            onClick={() => removeLine(line.product_id)}
                            className="w-9 h-9 rounded-lg bg-white/15 text-white flex items-center justify-center hover:bg-white/25"
                            title="Hapus item"
                        >
                            <Trash2 size={16} />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function MethodChip({ method, active, onClick }) {
    const Icons = { kas: Banknote, bank: Landmark, qris: QrCode };
    const Icon = Icons[method.type] ?? Icons[method.code] ?? CreditCard;
    return (
        <button
            onClick={onClick}
            className={`whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer ${
                active ? 'bg-accent text-on-accent' : 'bg-surface-3 text-muted hover:bg-surface-3/70'
            }`}
        >
            <Icon size={14} /> {method.name}
        </button>
    );
}

function AccountHint({ account }) {
    if (!account) return null;
    const Icon = account.type === 'bank' ? Landmark : Banknote;
    return (
        <p className="text-[11px] text-muted mt-1.5 flex items-center gap-1">
            <Icon size={11} />
            Masuk ke akun: {account.bank_name ? `${account.bank_name} — ${account.name}` : account.name}
        </p>
    );
}

function DiscountEditor({ totals, discountType, setDiscountType, discountRaw, setDiscountRaw }) {
    const [editing, setEditing] = useState(false);
    const percent = discountType === 'percent';

    const stop = () => setEditing(false);

    if (!editing) {
        return (
            <button
                type="button"
                onClick={() => setEditing(true)}
                title="Ubah diskon (persen atau nominal)"
                className="cursor-pointer hover:opacity-80 transition-opacity"
            >
                <PriceRow
                    value={totals.discount}
                    className="text-sm"
                    amountClassName="font-semibold text-negative"
                />
            </button>
        );
    }

    return (
        <div className="flex items-center gap-1.5" onBlur={(e) => e.currentTarget.contains(e.relatedTarget) || stop()}>
            <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => setDiscountType((t) => (t === 'percent' ? 'amount' : 'percent'))}
                title={percent ? 'Diskon persen (%) — klik untuk nominal' : 'Diskon nominal (Rp) — klik untuk persen'}
                className="w-9 h-9 rounded-lg text-[11px] font-bold shrink-0 cursor-pointer bg-accent text-on-accent hover:bg-accent-hover transition-colors"
            >
                {percent ? '%' : 'Rp'}
            </button>

            {percent ? (
                <div className="relative w-24">
                    <input
                        autoFocus
                        type="number"
                        min="0"
                        max="100"
                        inputMode="numeric"
                        className="input !py-1.5 pr-6 text-right text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        value={discountRaw}
                        onChange={(e) => setDiscountRaw(e.target.value)}
                        placeholder="0"
                    />
                    <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[11px] text-muted">%</span>
                </div>
            ) : (
                <div className="w-32">
                    <CurrencyInput value={discountRaw} onChange={setDiscountRaw} placeholder="0" />
                </div>
            )}

            <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={stop}
                title="Selesai"
                className="w-9 h-9 rounded-lg shrink-0 cursor-pointer bg-surface-2 text-muted hover:bg-surface-3 hover:text-content transition-colors"
            >
                <Check size={15} className="mx-auto" />
            </button>
        </div>
    );
}

function CheckoutPanel({
    cart, products = [], updateQty, removeLine,
    table, setTable, enableTable, tableNumbers,
    discountType, setDiscountType, discountRaw, setDiscountRaw,
    enablePpn, taxRate, enablePrepay,
    paymentMethod, onPaymentMethodChange, payMethods, defaultAccount, qrisId,
    totals, paidRaw, onPaidChange, paid, change,
    payOpen, onPayToggle, canSubmit, canDraft, submitting, onSubmit, onSaveDraft, onClear,
}) {
    const totalAmount = totals.total;
    const empty = cart.length === 0;

    let status = null;
    if (!empty && totalAmount > 0) {
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

    const productOf = (id) => products.find((p) => p.id === id);

    return (
        <div className="card sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto scrollbar-thin">
            <div className="flex items-center justify-between pb-3">
                <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                    <ShoppingCart size={16} /> Transaksi
                </h3>
                <button
                    onClick={onClear}
                    disabled={empty}
                    className="text-xs font-bold text-negative underline underline-offset-2 decoration-2 hover:text-red-700 dark:hover:text-red-300 transition-colors cursor-pointer disabled:opacity-40 disabled:no-underline disabled:text-muted disabled:cursor-not-allowed"
                    title="Kosongkan transaksi"
                >
                    Clear
                </button>
            </div>

            {empty ? null : (
                <>
                    <div className="mt-3 space-y-3 pr-0.5">
                        {enableTable && (
                            <div>
                                <span className="label">Meja</span>
                                <SearchSelect
                                    options={tableNumbers.map((num) => ({ value: `Meja ${num}`, label: `Meja ${num}` }))}
                                    value={table}
                                    onChange={setTable}
                                    placeholder="Cari nomor meja..."
                                    emptyLabel="Nomor meja tidak ditemukan."
                                    allLabel="Tanpa meja"
                                />
                                {table === '' && payOpen && (
                                    <p className="text-[11px] text-negative mt-1 flex items-center gap-1"><AlertCircle size={11} /> Nomor meja wajib dipilih sebelum menyimpan pembayaran.</p>
                                )}
                            </div>
                        )}

                        <div>
                            <span className="label">Rincian Pesanan</span>
                            <div className="space-y-2">
                                {cart.map((line) => (
                                    <div key={line.product_id} className="flex items-center gap-2 bg-surface-2 rounded-xl p-2">
                                        <LineThumb product={productOf(line.product_id)} />
                                        <div className="flex-1 min-w-0">
                                            <div className="text-sm font-semibold truncate">{line.name}</div>
                                            <div className="text-[11px] text-muted">
                                                {formatIDR(line.price)} · <span className="text-accent font-semibold">{formatIDR(line.price * line.qty)}</span>
                                            </div>
                                        </div>
                                        <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                                        <button onClick={() => removeLine(line.product_id)} className="text-negative hover:text-red-700 dark:hover:text-red-300 p-1" title="Hapus item">
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 pt-3">
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm text-muted">Subtotal</span>
                                <PriceRow value={totals.subtotal} className="text-sm" amountClassName="font-semibold" />
                            </div>

                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm text-muted">Diskon</span>
                                <DiscountEditor
                                    totals={totals}
                                    discountType={discountType}
                                    setDiscountType={setDiscountType}
                                    discountRaw={discountRaw}
                                    setDiscountRaw={setDiscountRaw}
                                />
                            </div>

                            {enablePpn && (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted">PPN {taxRate}%</span>
                                    <PriceRow value={totals.tax} className="text-sm" amountClassName="font-semibold" />
                                </div>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-3 mt-2 pt-2">
                            <span className="text-sm font-bold">Grand Total</span>
                            <PriceRow
                                value={totalAmount}
                                className="text-xl"
                                symbolClassName="font-bold"
                                amountClassName="font-bold"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={onPayToggle}
                            aria-expanded={payOpen}
                            className="w-full flex items-center justify-between gap-2 cursor-pointer group mt-2 px-3 py-2.5 rounded-lg bg-surface-2 hover:bg-surface-3 transition-colors"
                            title="Isi pembayaran"
                        >
                            <span className="flex items-center gap-1.5 text-sm font-bold">
                                Bayar
                                {status && !payOpen && <span className={`badge ${status.cls}`}>{status.text}</span>}
                            </span>
                            <Price value={totalAmount} className="text-sm font-semibold" />
                        </button>

                        {payOpen && (
                            <div className="mt-3 space-y-3">                                <div>
                                    <span className="label">Metode</span>
                                    <div className="flex flex-wrap gap-2">
                                        {payMethods.map((m) => (
                                            <MethodChip key={m.code} method={m} active={paymentMethod === m.code} onClick={() => onPaymentMethodChange(m.code)} />
                                        ))}
                                    </div>
                                    <AccountHint account={defaultAccount} />
                                </div>

                                {paymentMethod === 'qris' && qrisId && (
                                    <div className="bg-accent-soft rounded-lg p-3">
                                        <div className="flex items-center gap-2 mb-2 text-accent-ink font-bold text-sm">
                                            <QrCode size={15} /> QRIS — Scan untuk Bayar
                                        </div>
                                        <QrisQrCode value={qrisId} />
                                        <div className="text-[11px] text-muted mt-2 text-center">{qrisId}</div>
                                    </div>
                                )}

                                <div className="flex items-center gap-3">
                                    <span className="label w-24 shrink-0 !mb-0">Nominal</span>
                                    <div className="flex-1 flex items-center gap-2">
                                        <div className="flex-1 min-w-0">
                                            <CurrencyInput value={paidRaw} onChange={onPaidChange} />
                                        </div>
                                        {status && <span className={`badge shrink-0 ${status.cls}`}>{status.text}</span>}
                                    </div>
                                </div>

                                {change > 0 && <p className="text-[11px] text-muted text-right">Kembalian {formatIDR(change)}</p>}
                                {paid > 0 && paid < totalAmount && (
                                    <p className="text-[11px] text-right">
                                        {enablePrepay ? (
                                            <span className="text-muted">Bayar sebagian diperbolehkan — status Belum Lunas.</span>
                                        ) : (
                                            <span className="text-negative">Nominal harus melebihi total tagihan.</span>
                                        )}
                                    </p>
                                )}
                            </div>
                        )}

                        {payOpen ? (
                            <div className="flex items-center gap-2 mt-3">
                                <button
                                    type="button"
                                    className="btn btn-secondary flex-1 justify-center"
                                    disabled={!canDraft || submitting}
                                    onClick={onSaveDraft}
                                    title="Simpan sebagai draft tanpa pembayaran"
                                >
                                    {submitting ? 'Memproses...' : <><ReceiptText size={16} /> Draft</>}
                                </button>
                                <button
                                    className="btn btn-primary flex-1 justify-center"
                                    disabled={!canSubmit || submitting}
                                    onClick={onSubmit}
                                >
                                    {submitting ? 'Memproses...' : <><Save size={16} /> Simpan</>}
                                </button>
                            </div>
                        ) : (
                            <button
                                type="button"
                                className="btn btn-primary w-full justify-center mt-3"
                                disabled={!canDraft || submitting}
                                onClick={onSaveDraft}
                            >
                                {submitting ? 'Memproses...' : <><ReceiptText size={16} /> Draft</>}
                            </button>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
