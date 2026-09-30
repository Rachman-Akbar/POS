import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Printer, ReceiptText, Check,
    Minus, Plus, ShoppingCart, UtensilsCrossed, AlertCircle,
    Save, ChevronRight, ImageOff, ClipboardList, ChefHat, Trash2,
} from 'lucide-react';
import Layout from '../components/Layout';
import ProductCatalog, { ALL_CATEGORIES, catalogSectionKeys } from '../components/ProductCatalog';
import CurrencyInput from '../components/CurrencyInput';
import { PriceRow } from '../components/Price';
import SearchSelect from '../components/SearchSelect';
import CashierCustomerSelect from '../components/cashier/CashierCustomerSelect';
import ProductLinesList, { QtyStepper, LineThumb } from '../components/cashier/ProductLinesList';
import { PaymentButton, TOTAL_VALUE, TransactionCard } from '../components/cashier/PaymentParts';
import OrderDetailPage from '../components/cashier/OrderDetailPage';
import { ItemStatusBadge } from '../components/badges';
import { useAuth } from '../auth/AuthContext';
import { api, errorMessage, formatIDR, parseNumber } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifySuccess, notifyError, Swal, confirmAction } from '../utils/alerts';
import {
    DRAFT_FILTER, isDraftOrder, isUnpaidOrder, isVoidedOrder, orderProcessStatus,
    receivedOf, remainingOf, VOID_FILTER,
    PAYMENT_FILTER, paymentFilterOf, matchesPaymentFilter,
} from '../utils/order';

/** Pilihan filter status pembayaran di tabel Pesanan. */
const PAYMENT_FILTER_OPTIONS = [
    { value: PAYMENT_FILTER.All, label: 'Semua' },
    { value: PAYMENT_FILTER.Paid, label: 'Lunas' },
    { value: PAYMENT_FILTER.Unpaid, label: 'Belum Lunas' },
];

const NAV_ICONS = { kasir: UtensilsCrossed, pesanan: ClipboardList, dapur: ChefHat };

/**
 * Kolom papan dapur, urut sesuai tahap ItemStatus di backend.
 * Key mengikuti grup yang dikembalikan /kitchen/items.
 */
const KITCHEN_COLUMNS = [
    { key: 'waiting', label: 'Dipesan' },
    { key: 'cooking', label: 'Dimasak' },
    { key: 'sent', label: 'Dikirim' },
    { key: 'done', label: 'Selesai' },
];

/**
 * Tahap proses untuk filter di header halaman Pesanan.
 */
const PROCESS_STAGE_OPTIONS = [
    { name: 'pending', label: 'Dipesan' },
    { name: 'cooking', label: 'Dimasak' },
    { name: 'sent', label: 'Dikirim' },
    { name: 'done', label: 'Selesai' },
    { name: VOID_FILTER, label: 'Dibatalkan' },
];

/**
 * Cocokkan order dengan pilihan filter tahap di header Pesanan.
 */
function matchesStageFilter(order, stage) {
    if (stage === ALL_CATEGORIES) return true;

    return orderProcessStatus(order) === stage;
}

export default function CashierDashboard() {
    const queryClient = useQueryClient();
    const { can } = useAuth();

    // Koreksi transaksi hanya untuk pemegang `transaction.void` /
    // `transaction.refund`. Tombolnya disembunyikan dari kasir, tapi tetap
    // backend yang menolak kalau URL-nya diketik manual.
    const canVoid = can('transaction.void');
    const canRefund = can('transaction.refund');

    const [tab, setTab] = useState('kasir');
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
    const [customer, setCustomer] = useState(null);
    const [orderQuery, setOrderQuery] = useState('');
    const [orderStage, setOrderStage] = useState(ALL_CATEGORIES);
    const [paymentFilter, setPaymentFilter] = useState(PAYMENT_FILTER.All);
    const [detailOrderId, setDetailOrderId] = useState(null);
    const [settling, setSettling] = useState(false);

    const { data: products = [] } = useQuery({
        queryKey: ['products'],
        queryFn: async () => (await api.get('/products')).data.data,
    });

    const { data: settings } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => (await api.get('/settings')).data.data,
    });

    const { data: orders = [] } = useQuery({
        queryKey: ['cashier-orders'],
        queryFn: async () => (await api.get('/orders/transactions')).data.data,
        refetchInterval: 15_000,
    });

    const { data: kitchen = {} } = useQuery({
        queryKey: ['kitchen-items'],
        queryFn: async () => (await api.get('/kitchen/items')).data.data,
        refetchInterval: 10_000,
    });

    const flags = settings?.cashier ?? {};
    const showFavorites = flags.cashier_show_favorites ?? true;
    const showStock = flags.cashier_show_stock ?? true;
    const enableTable = flags.cashier_enable_table ?? true;
    const enablePpn = flags.cashier_enable_ppn ?? true;
    const enablePrepay = flags.cashier_enable_prepay ?? false;
    const enableCustomer = flags.cashier_enable_customer ?? true;
    const taxRate = Number(settings?.ppn_rate ?? 11);
    const tableNumbers = settings?.table_numbers ?? Array.from({ length: 20 }, (_, i) => String(i + 1));

    // Disimpan sebagai id, bukan objek, supaya modal ikut ter-update begitu
    // order dilunasi atau draft diselesaikan dan daftar di-refetch.
    const detailOrder = useMemo(
        () => orders.find((order) => order.id === detailOrderId) ?? null,
        [orders, detailOrderId],
    );

    const kitchenTotal = KITCHEN_COLUMNS.reduce(
        (sum, column) => sum + (kitchen[column.key]?.length ?? 0),
        0,
    );

    /**
     * Order yang cocok dengan pencarian header, sebelum filter tahap
     * diterapkan. Dipisah karena jumlah per tahap di dropdown filter dihitung
     * dari daftar ini: user tetap melihat sebaran tahap yang sebenarnya dari
     * kata kunci yang diketiknya, walau ia sedang menyaring satu tahap.
     */
    const searchMatchedOrders = useMemo(() => {
        const needle = orderQuery.trim().toLowerCase();

        return orders.filter((order) => {
            if (needle === '') return true;

            const customer = order.customer?.company_name || order.customer?.name || '';
            const items = (order.items ?? []).map((item) => item.product?.name ?? '').join(' ');
            const haystack = [
                order.order_number,
                order.invoice?.invoice_number,
                order.table_number,
                customer,
                items,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(needle);
        });
    }, [orders, orderQuery]);

    /** Order setelah pencarian header, filter tahap proses, dan filter lunas. */
    const stageMatchedOrders = useMemo(
        () => searchMatchedOrders.filter((order) => matchesStageFilter(order, orderStage)),
        [searchMatchedOrders, orderStage],
    );

    /**
     * Jumlah order per kelompok status pembayaran, dihitung dari hasil filter
     * tahap dan pencarian supaya angka di chip selalu yang bisa dibuka.
     */
    const paymentCounts = useMemo(() => {
        const counts = new Map();
        stageMatchedOrders.forEach((order) => {
            const key = paymentFilterOf(order);
            counts.set(key, (counts.get(key) ?? 0) + 1);
        });

        return counts;
    }, [stageMatchedOrders]);

    const visibleOrders = useMemo(
        () => stageMatchedOrders.filter((order) => matchesPaymentFilter(order, paymentFilter)),
        [stageMatchedOrders, paymentFilter],
    );

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
            onOrderCreated: () => queryClient.invalidateQueries({ queryKey: ['cashier-orders'] }),
            onPaymentProcessed: () => {
                queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
                queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
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

    /**
     * Baris keranjang dis enriching dengan produk katalog supaya kartu grid
     * bisa memakai foto dan data stok yang sama dengan katalog.
     */
    const cartLines = useMemo(
        () =>
            cart.map((line) => ({
                ...line,
                product: products.find((p) => p.id === line.product_id),
            })),
        [cart, products],
    );

    const resetCheckout = () => {
        setCart([]);
        setTable('');
        setDiscountRaw('');
        setDiscountType('percent');
        setPaidRaw('');
        setPaidTouched(false);
        setPayOpen(false);
        setCustomer(null);
    };

    const submitOrder = async () => {
        if (!canSubmit) return;
        setSubmitting(true);
        try {
            await api.post('/orders', {
                table_number: enableTable ? table : undefined,
                payment_type: 'pay_now',
                discount: totals.discount,
                tax_rate: enablePpn ? Number(taxRate) || 0 : 0,
                payment_method: paymentMethod,
                paid_amount: paid,
                customer_id: customer?.id,
                items: cart.map(({ product_id, qty }) => ({ product_id, qty })),
            });
            resetCheckout();
            notifySuccess('Transaksi lunas, pesanan dikirim ke dapur.');
            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
        } catch (err) {
            notifyError('Transaksi gagal', err.response?.data?.message ?? 'Gagal memproses transaksi.');
        } finally {
            setSubmitting(false);
        }
    };

    /**
     * Simpan keranjang sebagai draft: order tersimpan yang belum diproses.
     * Tidak membuat invoice, tidak membawah stok, dan tidak masuk dapur.
     * Kasir melanjutkannya dari tab Pesanan lewat detail.
     */
    const saveDraft = async () => {
        if (!canDraft) return;
        setSubmitting(true);
        try {
            await api.post('/orders/draft', {
                table_number: enableTable ? table : undefined,
                discount: totals.discount,
                tax_rate: enablePpn ? Number(taxRate) || 0 : 0,
                customer_id: customer?.id,
                items: cart.map(({ product_id, qty }) => ({ product_id, qty })),
            });
            resetCheckout();
            notifySuccess('Draft disimpan. Belum diproses — lanjutkan dari tab Pesanan.');
            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
        } catch (err) {
            notifyError('Draft gagal', err.response?.data?.message ?? 'Gagal menyimpan draft.');
        } finally {
            setSubmitting(false);
        }
    };

    /**
     * Terima pelunasan: kirim nominal yang diinput kasir. Bila nominal
     * melebihi sisa tagihan, kelebihan dicatat sebagai kembalian dan
     * dikembalikan ke pelanggan, bukan menambah pendapatan.
     */
    const settle = async (order, amount) => {
        const target = payMethods.find((m) => m.code === (selected[order.id] ?? payMethods[0]?.code ?? 'cash')) ?? payMethods[0] ?? { code: 'cash' };
        setSettling(true);
        try {
            const { data } = await api.post(`/payments/orders/${order.id}/settle`, {
                payment_method: target.code,
                ...(amount != null ? { amount } : {}),
            });
            const change = Number(data?.data?.change ?? 0);

            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });

            if (change > 0) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Pembayaran diterima',
                    html: `Kembalian untuk pelanggan <b>${formatIDR(change)}</b>`,
                    confirmButtonText: 'Selesai',
                    confirmButtonColor: '#ea580c',
                });
            } else {
                notifySuccess(`Pembayaran ${order.invoice?.invoice_number ?? order.order_number} berhasil.`);
            }
        } catch (err) {
            notifyError('Pembayaran gagal', err.response?.data?.message ?? 'Gagal memproses pembayaran.');
        } finally {
            setSettling(false);
        }
    };

    /**
     * Lanjutkan draft: menerbitkan invoice dan menarik stok, lalu pesanan masuk
     * dapur. Tidak ada pembayaran di sini — pelunasan dilakukan belakangan,
     * setelah pelanggan selesai dan datang ke kasir (pay-later). Halaman detail
     * otomatis berganti ke alur "Terima Pelunasan" begitu order terrefetch.
     */
    const finalizeDraft = async (order) => {
        setSettling(true);
        try {
            await api.post(`/orders/${order.id}/finalize`);
            notifySuccess(`Draft ${order.order_number} dilanjutkan dan dikirim ke dapur.`);
            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
        } catch (err) {
            notifyError('Draft gagal diproses', err.response?.data?.message ?? 'Gagal melanjutkan draft.');
        } finally {
            setSettling(false);
        }
    };

    /** Hapus draft sebelum diproses: pesanan tidak akan pernah masuk dapur. */
    const deleteDraft = async (order) => {
        const confirmed = await confirmAction(
            'Hapus Draft?',
            `Draft ${order.order_number} akan dihapus. Pesanan tidak akan diproses dan tidak bisa dipulihkan.`,
            'Ya, hapus',
        );
        if (!confirmed) return;

        setSettling(true);
        try {
            await api.delete(`/orders/${order.id}`);
            notifySuccess(`Draft ${order.order_number} dihapus.`);
            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            setDetailOrderId(null);
        } catch (err) {
            notifyError('Hapus draft gagal', errorMessage(err, 'Gagal menghapus draft.'));
        } finally {
            setSettling(false);
        }
    };

    /**
     * Pembatalan penuh: uang yang sudah masuk dikembalikan, stok dikembalikan,
     * dan pesanan ditutup. Dipisah dari `settle`/`finalizeDraft` karena efeknya
     * menyentuh tiga modul sekaligus (pesanan, faktur, dan dapur).
     */
    const voidOrder = async (order, reason) => {
        setSettling(true);
        try {
            const { data } = await api.post(`/orders/${order.id}/void`, { reason });

            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
            setDetailOrderId(null);

            notifySuccess(
                `Transaksi ${order.invoice?.invoice_number ?? order.order_number} dibatalkan.`,
                Number(data?.data?.refunded ?? 0) > 0
                    ? `${formatIDR(data.data.refunded)} dikembalikan ke pelanggan.`
                    : '',
            );
        } catch (err) {
            notifyError('Pembatalan gagal', errorMessage(err, 'Gagal membatalkan transaksi.'));
        } finally {
            setSettling(false);
        }
    };

    /** Retur sebagian: mengembalikan uang pada satu penerimaan pembayaran. */
    const refundOrder = async (order, receiptId, amount, reason) => {
        setSettling(true);
        try {
            await api.post(`/orders/${order.id}/refunds/${receiptId}`, { amount, reason });

            queryClient.invalidateQueries({ queryKey: ['cashier-orders'] });
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });

            notifySuccess(
                `${formatIDR(amount)} diretur dari ${order.invoice?.invoice_number ?? order.order_number}.`,
                reason,
            );
        } catch (err) {
            notifyError('Retur gagal', errorMessage(err, 'Gagal memproses retur.'));
        } finally {
            setSettling(false);
        }
    };

    const printReceipt = (order) => {
        const customerLabel = order.customer
            ? order.customer.company_name || order.customer.name
            : 'Umum';
        const text = [
            `======= STRUK - ${settings?.store_name ?? 'POS'} =======`,
            `No Faktur : ${order.invoice?.invoice_number ?? '-'}`,
            `No Pesanan: ${order.order_number}`,
            `Meja      : ${order.table_number ?? '-'}`,
            `Pelanggan : ${customerLabel}`,
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

    // Kolom pencarian dan filter di header mengikuti tab yang sedang aktif:
    // di Kasir ia mencari produk, di Pesanan ia mencari nomor pesanan dan
    // kategori produk digantikan filter tahap proses.
    // Halaman detail pesanan adalah halaman penuh, bukan tab: kolom pencarian
    // dan filter disembunyikan dan navigasi tetap menyorot tab Pesanan.
    // `detailOrder` bisa null ketika id yang dibuka tidak ada lagi di hasil
    // refetch (mis. order dihapus di halaman lain). Halaman detail hanya
    // dirender bila order-nya benar-benar ada: `OrderDetailPage` melakukan
    // early return sebelum hooks-nya, jadi merendernya tanpa order akan
    // melanggar Rules of Hooks dan membuat React error.
    const onOrderDetail = Boolean(detailOrderId) && Boolean(detailOrder);

    // Halaman "Cek Pesanan" hanya bermakna bila keranjang masih berisi produk.
    // Keranjang bisa kosong dari tiga arah: produk terakhir dihapus dari halaman
    // itu sendiri, transaksi disimpan, atau draft disimpan. Tanpa pengembalian
    // otomatis, kasir tersangkut di panel yang tidak punya tombol kembali dan
    // tidak bisa menambahkan produk lagi.
    //
    // `visibleTab` menjaga render tetap benar pada frame yang sama, sedangkan
    // useEffect menyelaraskan state `tab` supaya tidak ada lagi yang membaca
    // halaman Cek Pesanan yang sudah kosong.
    const visibleTab = tab === 'cart' && cart.length === 0 ? 'kasir' : tab;

    useEffect(() => {
        if (tab !== visibleTab) {
            setTab(visibleTab);
            setPayOpen(false);
        }
    }, [tab, visibleTab]);

    const onOrderList = visibleTab === 'pesanan' && !onOrderDetail;
    // Papan Dapur tidak punya katalog: pencarian produk dan filter kategori
    // tidak ada gunanya di sana, begitu juga pengalih tampilan dan buka semua.
    const onKitchenBoard = visibleTab === 'dapur';
    const onCatalog = !onOrderList && !onKitchenBoard && !onOrderDetail;

    const stageCategories = useMemo(() => {
        const counts = new Map();
        searchMatchedOrders.forEach((order) => {
            const status = orderProcessStatus(order);
            counts.set(status, (counts.get(status) ?? 0) + 1);
        });

        return [
            { name: DRAFT_FILTER, label: 'Draft', count: counts.get(DRAFT_FILTER) ?? 0 },
            ...PROCESS_STAGE_OPTIONS.map((stage) => ({
                name: stage.name,
                label: stage.label,
                count: counts.get(stage.name) ?? 0,
            })),
        ];
    }, [searchMatchedOrders]);

    const header = {
        navLabel: onOrderDetail ? 'Detail Pesanan' : 'Pesanan',
        showCatalog: !onKitchenBoard && !onOrderDetail,
        mode: onCatalog ? mode : undefined,
        onModeChange: onCatalog ? setMode : undefined,
        allOpen: onCatalog ? allSectionsOpen : undefined,
        onToggleAll: onCatalog ? toggleAllSections : undefined,
        query: onOrderList ? orderQuery : query,
        onQueryChange: onOrderList ? setOrderQuery : onCatalog ? setQuery : undefined,
        searchPlaceholder: onOrderList
            ? 'Cari no pesanan, faktur, meja, atau pelanggan...'
            : 'Cari produk...',
        categories: onOrderList ? stageCategories : categories,
        category: onOrderList ? orderStage : category,
        onCategoryChange: onOrderList ? setOrderStage : setCategory,
        filterAllLabel: onOrderList ? 'Semua Tahap' : 'Semua Kategori',
        filterPlaceholder: onOrderList ? 'Cari tahap proses...' : 'Cari kategori...',
        filterEmptyLabel: onOrderList ? 'Tahap tidak ditemukan.' : 'Kategori tidak ditemukan.',
        favoritesCount: onOrderList
            ? 0
            : products.filter((product) => product.is_favorite).length,
        navItems: [
            { key: 'kasir', label: 'Kasir', icon: NAV_ICONS.kasir },
            { key: 'pesanan', label: 'Pesanan', icon: NAV_ICONS.pesanan },
            { key: 'dapur', label: 'Dapur', icon: NAV_ICONS.dapur },
        ],
        activeNav: onOrderDetail ? 'pesanan' : visibleTab,
        onNavChange: (next) => {
            setDetailOrderId(null);
            setTab(next);
        },
    };

    const checkoutSidebar = (
        <CheckoutPanel
            cart={cart}
            cartLines={cartLines}
            updateQty={updateQty}
            removeLine={removeLine}
            table={table}
            setTable={setTable}
            enableTable={enableTable}
            enableCustomer={enableCustomer}
            customer={customer}
            onCustomerChange={setCustomer}
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
            onSubmit={submitOrder}
            onSaveDraft={saveDraft}
            onViewChange={setTab}
        />
    );

    const detailPage = (
        <OrderDetailPage
            order={detailOrder}
            view={mode}
            onViewChange={setMode}
            payMethods={payMethods}
            method={
                detailOrder
                    ? (selected[detailOrder.id] ?? payMethods[0]?.code ?? 'cash')
                    : (payMethods[0]?.code ?? 'cash')
            }
            onMethodChange={(code) =>
                setSelected((prev) => ({
                    ...prev,
                    [detailOrder?.id]: code,
                }))
            }
            qrisId={settings?.qris_id ?? null}
            onSettle={(amount) => detailOrder && settle(detailOrder, amount)}
            onFinalize={() => detailOrder && finalizeDraft(detailOrder)}
            onDelete={() => detailOrder && deleteDraft(detailOrder)}
            onPrint={() => detailOrder && printReceipt(detailOrder)}
            onClose={() => setDetailOrderId(null)}
            busy={settling}
            correction={{
                canVoid,
                canRefund,
                onVoid: (reason) => detailOrder && voidOrder(detailOrder, reason),
                onRefund: (receiptId, amount, reason) =>
                    detailOrder && refundOrder(detailOrder, receiptId, amount, reason),
            }}
        />
    );

    return (
        <Layout header={header}>
            {onOrderDetail ? (
                detailPage
            ) : (
                <>
                    {visibleTab === 'kasir' && (
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

                    {visibleTab === 'cart' && (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div className="xl:col-span-2">
                        <div className="card">
                            <div className="flex items-center justify-between gap-3 pb-3 mb-4">
                                <div>
                                    <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                                        <ShoppingCart size={16} /> Cek Pesanan
                                    </h3>
                                    <p className="text-[11px] text-muted mt-0.5">
                                        Keranjang aktif, belum disimpan sebagai pesanan.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="badge badge-pending">{cart.length} item</span>
                                </div>
                            </div>

                            {cart.length === 0 ? (
                                <p className="text-muted text-sm text-center py-10">Keranjang kosong. Tambahkan produk dari halaman Kasir.</p>
                            ) : (
                                <ProductLinesList
                                    view={mode}
                                    lines={cartLines}
                                    updateQty={updateQty}
                                    removeLine={removeLine}
                                />
                            )}
                        </div>
                    </div>

                    {checkoutSidebar}
                </div>
            )}

            {visibleTab === 'pesanan' && (
                <div className="card">
                    <div className="flex flex-wrap items-center justify-between gap-2 pb-3 mb-4">
                        <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                            <ClipboardList size={16} /> Pesanan
                        </h3>

                        <div className="flex flex-wrap items-center gap-2">
                            {/*
                             * Filter status pembayaran. Berdampingan dengan filter
                             * tahap di header karena keduanya menyaring baris yang
                             * sama: tahap melihat sisi dapur, ini sisi kas.
                             */}
                            <div className="flex items-center gap-1.5">
                                <span className="text-xs font-semibold text-muted">Status Bayar</span>
                                {PAYMENT_FILTER_OPTIONS.map((option) => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => setPaymentFilter(option.value)}
                                        title={`Filter ${option.label}`}
                                        className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                                            paymentFilter === option.value
                                                ? 'bg-accent text-on-accent'
                                                : 'bg-surface-2 text-muted hover:bg-surface-3'
                                        }`}
                                    >
                                        {option.label}
                                        <span className="ml-1 tabular-nums opacity-70">
                                            {option.value === PAYMENT_FILTER.All
                                                ? stageMatchedOrders.length
                                                : (paymentCounts.get(option.value) ?? 0)}
                                        </span>
                                    </button>
                                ))}
                            </div>

                            <span className="text-xs text-muted">
                                {visibleOrders.length} dari {orders.length} transaksi
                            </span>
                        </div>
                    </div>

                    {visibleOrders.length === 0 ? (
                        <div className="text-muted text-center py-10">
                            {orders.length === 0
                                ? 'Belum ada transaksi.'
                                : 'Tidak ada pesanan yang cocok dengan pencarian atau filter.'}
                        </div>
                    ) : (
                        <div className="border border-line rounded-xl overflow-x-auto bg-surface">
                            <table className="w-full">
                                <thead className="border-b border-line">
                                    <tr>
                                        <th className="table-head">No Pesanan</th>
                                        <th className="table-head">Pesanan</th>
                                        <th className="table-head text-right">Bayar</th>
                                        <th className="table-head text-right">Lunas</th>
                                        <th className="table-head text-center">Proses</th>
                                        <th className="table-head text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {visibleOrders.map((order) => {
                                        const draft = isDraftOrder(order);
                                        const received = receivedOf(order);
                                        const remaining = remainingOf(order);
                                        const unpaid = isUnpaidOrder(order);
                                        const customer = order.customer?.company_name || order.customer?.name;

                                        return (
                                            <tr
                                                key={order.id}
                                                onClick={() => setDetailOrderId(order.id)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter' || event.key === ' ') {
                                                        event.preventDefault();
                                                        setDetailOrderId(order.id);
                                                    }
                                                }}
                                                tabIndex={0}
                                                role="button"
                                                aria-label={`Detail pesanan ${order.order_number ?? ''}`}
                                                className="cursor-pointer hover:bg-surface-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent transition-colors"
                                                title="Klik untuk melihat detail"
                                            >
                                                <td className="table-cell">
                                                    <div className="font-semibold text-xs">
                                                        {order.order_number ?? '-'}
                                                    </div>
                                                    <div className="text-xs text-muted">
                                                        {draft
                                                            ? 'Belum diproses · tanpa faktur'
                                                            : order.invoice?.invoice_number ?? '-'}
                                                    </div>
                                                    <div className="text-[11px] text-muted">
                                                        Meja {order.table_number ?? '-'}
                                                        {customer ? ` · ${customer}` : ''}
                                                    </div>
                                                </td>
                                                <td className="table-cell">
                                                    <div className="text-xs max-w-[18rem] truncate">
                                                        {(order.items ?? [])
                                                            .map((item) => `${item.qty}\u00d7 ${item.product?.name}`)
                                                            .join(', ') || '-'}
                                                    </div>
                                                    <div className="text-[11px] text-muted font-semibold">
                                                        {formatIDR(order.total_amount)}
                                                    </div>
                                                </td>
                                                <td className="table-cell text-right text-positive whitespace-nowrap">
                                                    {formatIDR(received)}
                                                </td>
                                                <td className="table-cell text-right whitespace-nowrap">
                                                    {draft ? (
                                                        <span className="badge badge-unpaid">Belum Bayar</span>
                                                    ) : unpaid ? (
                                                        <span className="font-bold text-accent">
                                                            {formatIDR(remaining)}
                                                        </span>
                                                    ) : (
                                                        <span className="badge badge-paid">Lunas</span>
                                                    )}
                                                </td>
                                                <td className="table-cell text-center">
                                                    {draft ? (
                                                        <span className="badge badge-unpaid">Draft</span>
                                                    ) : (
                                                        <ItemStatusBadge status={orderProcessStatus(order)} />
                                                    )}
                                                </td>
                                                <td className="table-cell text-right">
                                                    {!draft && (
                                                        <button
                                                            className="btn btn-ghost !px-2 !py-1.5"
                                                            onClick={(event) => {
                                                                event.stopPropagation();
                                                                printReceipt(order);
                                                            }}
                                                            title="Cetak Struk"
                                                        >
                                                            <Printer size={14} />
                                                        </button>
                                                    )}
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

            {visibleTab === 'dapur' && (
                <div className="card">
                    <div className="flex items-center justify-between gap-2 pb-3 mb-4">
                        <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                            <ChefHat size={16} /> Dapur
                        </h3>
                        <span className="text-xs text-muted">Diubah oleh koki di halaman Dapur</span>
                    </div>

                    {kitchenTotal === 0 ? (
                        <div className="text-muted text-center py-10">Tidak ada pesanan menunggu dapur.</div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-5 gap-3">
                            {KITCHEN_COLUMNS.map((column) => {
                                const list = kitchen[column.key] ?? [];

                                return (
                                    <div key={column.key} className="rounded-xl border border-line bg-surface-2 p-2">
                                        <div className="flex items-center justify-between px-2 py-1.5 mb-2">
                                            <span className="text-xs font-bold uppercase tracking-wide">
                                                {column.label}
                                            </span>
                                            <span className="badge badge-pending !px-1.5 !py-0 !text-[10px]">
                                                {list.length}
                                            </span>
                                        </div>
                                        <div className="space-y-1.5">
                                            {list.length === 0 ? (
                                                <p className="text-[11px] text-muted text-center py-2">&#8212;</p>
                                            ) : (
                                                list.map((item) => (
                                                    <div key={item.id} className="rounded-lg bg-surface p-2">
                                                        <div className="text-xs font-semibold truncate">
                                                            {item.qty}\u00d7 {item.product?.name}
                                                        </div>
                                                        <div className="text-[11px] text-muted truncate">
                                                            Meja {item.order?.table_number ?? '-'} \u00b7{' '}
                                                            {item.order?.order_number ?? '-'}
                                                        </div>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
                </>
            )}
        </Layout>
    );
}


/**
 * Diskon hanya bisa diisi bila user punya `pos.discount`. Backend menolak order
 * berdiskon untuk role lain, jadi menyembunyikan editornya mencegah kasir biasa
 * tidak sengaja tersendat saat checkout.
 */
function DiscountEditor({ totals, discountType, setDiscountType, discountRaw, setDiscountRaw }) {
    const { can } = useAuth();
    const [editing, setEditing] = useState(false);
    const percent = discountType === 'percent';
    const allowed = can('pos.discount');

    const stop = () => setEditing(false);

    if (!allowed) {
        return (
            <PriceRow
                value={0}
                className={TOTAL_VALUE}
                amountClassName="font-semibold text-negative"
            />
        );
    }

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
                    className={TOTAL_VALUE}
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
                <div className={TOTAL_VALUE}>
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

/**
 * Tombol cek pesanan di sebelah kanan label Rincian Pesanan.
 *
 * Navigasi ke Draft dan Riwayat sudah pindah ke dropdown header, jadi tombol
 * ini hanya punya satu fungsi: membuka tampilan penuh keranjang.
 */
function CheckOrdersButton({ onClick, itemCount = 0 }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="btn btn-ghost !px-2 !py-1 flex items-center gap-1 cursor-pointer shrink-0 relative"
            title={`Cek Pesanan (${itemCount} item)`}
            aria-label={`Cek Pesanan, ${itemCount} item`}
        >
            <ShoppingCart size={14} />
            {itemCount > 0 && (
                <span className="absolute -top-1.5 -right-1.5 min-w-[16px] h-[16px] px-1 rounded-full bg-accent text-on-accent text-[9px] font-bold flex items-center justify-center">
                    {itemCount}
                </span>
            )}
        </button>
    );
}

function CheckoutPanel({
    cart, cartLines, updateQty, removeLine,
    table, setTable, enableTable, tableNumbers,
    enableCustomer, customer, onCustomerChange,
    discountType, setDiscountType, discountRaw, setDiscountRaw,
    enablePpn, taxRate, enablePrepay,
    paymentMethod, onPaymentMethodChange, payMethods, qrisId,
    totals, paidRaw, onPaidChange, paid, change,
    payOpen, onPayToggle, canSubmit, canDraft, submitting, onSubmit, onSaveDraft, onClear,
    onViewChange,
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

    return (
        <TransactionCard title="Transaksi" icon={ShoppingCart} actions={
            <button
                onClick={onClear}
                disabled={empty}
                className="text-xs font-bold text-negative underline underline-offset-2 decoration-2 hover:text-red-700 dark:hover:text-red-300 transition-colors cursor-pointer disabled:opacity-40 disabled:no-underline disabled:text-muted disabled:cursor-not-allowed"
                title="Kosongkan transaksi"
            >
                Clear
            </button>
        }>
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

                        {enableCustomer && (
                            <CashierCustomerSelect selected={customer} onChange={onCustomerChange} />
                        )}

                        <div className="flex items-center justify-between gap-2">
                            <span className="label !mb-0">Rincian Pesanan</span>
                            <CheckOrdersButton
                                onClick={() => onViewChange('cart')}
                                itemCount={cart.reduce((sum, line) => sum + line.qty, 0)}
                            />
                        </div>
                        <div className="space-y-2">
                            {cartLines.map((line) => (
                                <div key={line.product_id} className="flex items-center gap-2 bg-surface-2 rounded-xl p-2">
                                    <LineThumb product={line.product} />
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-semibold truncate">{line.name}</div>
                                        <div className="text-[11px] text-muted tabular-nums">
                                            {formatIDR(line.price)} &times; {line.qty}
                                        </div>
                                    </div>
                                    <PriceRow
                                        value={line.price * line.qty}
                                        className="shrink-0"
                                        amountClassName="text-accent font-semibold"
                                    />
                                    <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-4 pt-3">
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm text-muted">Subtotal</span>
                                <PriceRow
                                    value={totals.subtotal}
                                    className={TOTAL_VALUE}
                                    amountClassName="font-semibold"
                                />
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
                                    <PriceRow
                                        value={totals.tax}
                                        className={TOTAL_VALUE}
                                        amountClassName="font-semibold"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-3 mt-2 pt-2">
                            <span className="text-sm font-bold">Grand Total</span>
                            <PriceRow
                                value={totalAmount}
                                className={`${TOTAL_VALUE} text-xl`}
                                symbolClassName="font-bold"
                                amountClassName="font-bold"
                            />
                        </div>

                        <PaymentButton
                            open={payOpen}
                            onToggle={onPayToggle}
                            methods={payMethods}
                            method={paymentMethod}
                            onMethodChange={onPaymentMethodChange}
                            qrisId={paymentMethod === 'qris' ? qrisId : null}
                            amount={{
                                raw: paidRaw,
                                onChange: onPaidChange,
                                placeholder: String(Math.round(totalAmount)),
                            }}
                            status={status}
                            change={change}
                            hint={
                                paid > 0 && paid < totalAmount ? (
                                    <p className="text-[11px] text-right">
                                        {enablePrepay ? (
                                            <span className="text-muted">Bayar sebagian diperbolehkan — status Belum Lunas.</span>
                                        ) : (
                                            <span className="text-negative">Nominal harus melebihi total tagihan.</span>
                                        )}
                                    </p>
                                ) : null
                            }
                        />

                        <div className="flex items-center gap-2 mt-3">
                            <button
                                type="button"
                                className="btn btn-secondary flex-1 justify-center"
                                disabled={!canDraft || submitting}
                                onClick={onSaveDraft}
                                title="Simpan sementara tanpa diproses — lanjutkan dari tab Pesanan"
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
                    </div>
                </>
            )}
        </TransactionCard>
    );
}
