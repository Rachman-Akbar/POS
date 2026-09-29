import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { LayoutGrid, Table2 } from 'lucide-react';
import Layout from '../components/Layout';
import { api } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifyError } from '../utils/alerts';
import { ITEM_STATUS } from '../components/badges';

const STAGES = [
    { key: 'pending', label: 'Dipesan', band: 'bg-gray-600' },
    { key: 'cooking', label: 'Dimasak', band: 'bg-amber-500' },
    { key: 'sent', label: 'Dikirim', band: 'bg-blue-600' },
    { key: 'done', label: 'Selesai', band: 'bg-emerald-500' },
];

const orderStage = (order) => {
    const items = order.items ?? [];
    if (items.length === 0) return null;
    if (items.every((i) => i.status === 'done')) return 'done';
    if (items.some((i) => i.status === 'pending')) return 'pending';
    if (items.some((i) => i.status === 'cooking')) return 'cooking';
    if (items.some((i) => i.status === 'sent')) return 'sent';
    return 'done';
};

const KITCHEN_MODES = [
    { key: 'board', label: 'Kanban Board', icon: LayoutGrid },
    { key: 'table', label: 'Tabel', icon: Table2 },
];

const formatTime = (value, withDate = false) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    const options = { hour: '2-digit', minute: '2-digit' };
    if (withDate) options.day = '2-digit', options.month = 'short';
    return date.toLocaleTimeString('id-ID', options);
};

/**
 * Warna tiap opsi dropdown status.
 *
 * Opsi <option> tidak mewarisi gaya <select> secara konsisten di semua browser,
 * sehingga tanpa warna eksplisit isinya ikut menumpang warna select dan tidak
 * kelihatan. Warna di sini mengikuti tahap agar daftar isi selalu terbaca.
 */
const STATUS_OPTION_TONE = {
    pending: 'bg-gray-100 text-gray-800',
    cooking: 'bg-amber-100 text-amber-900',
    sent: 'bg-blue-100 text-blue-900',
    done: 'bg-emerald-100 text-emerald-900',
};

/**
 * Editor status satu menu, dipakai di mode Kanban Board maupun Tabel.
 *
 * Memakai <select> supaya koki bisa memindahkan tahap maju maupun mundur, dan
 * tersedia langsung pada tiap baris menu tanpa harus membuka Detail. Warna
 * dropdown mengikuti tahap agar mudah dibaca sekilas.
 */
function ItemStatusSelect({ item, onChange, className = '' }) {
    return (
        <span className={`relative inline-flex shrink-0 ${className}`}>
            <select
                value={item.status}
                onChange={(e) => onChange(item, e.target.value)}
                onClick={(e) => e.stopPropagation()}
                className={`status-select status-${item.status} !pr-4`}
                title="Ubah status menu"
            >
                {Object.entries(ITEM_STATUS).map(([key, meta]) => (
                    <option key={key} value={key} className={STATUS_OPTION_TONE[key]}>
                        {meta.label}
                    </option>
                ))}
            </select>
            <span className="pointer-events-none absolute right-1 top-1/2 -translate-y-1/2 text-[9px] leading-none opacity-70">
                &#9662;
            </span>
        </span>
    );
}

export default function KitchenDashboard() {
    const queryClient = useQueryClient();
    const [query, setQuery] = useState('');
    const [view, setView] = useState('board');
    const [selectedId, setSelectedId] = useState(null);
    const [collapsedKeys, setCollapsedKeys] = useState({});
    const [focusStage, setFocusStage] = useState(null);

    const { data: items = { waiting: [], cooking: [], sent: [], done: [] }, isLoading } = useQuery({
        queryKey: ['kitchen-items'],
        queryFn: async () => (await api.get('/kitchen/items')).data.data,
        refetchInterval: 10_000,
    });

    useEffect(() => {
        const channel = listenToOrders({
            onOrderCreated: () => {
                queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
                void playBeep();
            },
            onItemUpdated: () => queryClient.invalidateQueries({ queryKey: ['kitchen-items'] }),
        });
        return () => {
            channel?.stopListening?.('order.created');
            channel?.stopListening?.('item.status.updated');
        };
    }, [queryClient]);

    const playBeep = () => {
        try {
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch {
            // Audio tidak tersedia.
        }
    };

    const orders = useMemo(() => {
        const grouped = new Map();
        [...items.waiting, ...items.cooking, ...items.sent, ...items.done].forEach((item) => {
            const order = item.order;
            if (!grouped.has(order.id)) {
                grouped.set(order.id, { ...order, items: [], created_at: order.created_at });
            }
            grouped.get(order.id).items.push(item);
        });
        return [...grouped.values()].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    }, [items]);

    const boardGroups = STAGES.map((stage) => ({
        ...stage,
        orders: orders.filter((o) => o.items.some((i) => i.status === stage.key)),
    }));

    // Saat satu status dipilih, board menyempit hanya ke kolom status itu dan
    // kolomnya melebar memenuhi layar.
    const shownStages = focusStage ? boardGroups.filter((s) => s.key === focusStage) : boardGroups;

    const filteredOrders = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? orders.filter((o) => o.order_number.toLowerCase().includes(q)) : orders;
    }, [orders, query]);

    const updateStatus = async (item, status) => {
        if (item.status === status) return;

        queryClient.setQueryData(['kitchen-items'], (old) => {
            if (!old) return old;
            const updated = {};
            Object.keys(old).forEach((group) => {
                updated[group] = old[group].map((i) => (i.id === item.id ? { ...i, status } : i));
            });
            return updated;
        });

        try {
            await api.patch(`/kitchen/items/${item.id}/status`, { status });
        } catch (err) {
            queryClient.invalidateQueries({ queryKey: ['kitchen-items'] });
            notifyError('Gagal', err.response?.data?.message ?? 'Gagal memperbarui status.');
        }
    };

    const toggleCollapsed = (orderId) => {
        setCollapsedKeys((m) => ({ ...m, [orderId]: !m[orderId] }));
    };

    const renderItemRow = (item) => {
        return (
            <div key={item.id} className="flex items-center gap-2 bg-surface-2 px-2 py-1.5">
                <span className="text-accent font-black text-sm shrink-0 tabular-nums">{item.qty}&times;</span>
                <span className="flex-1 min-w-0 text-sm font-semibold truncate">{item.product?.name}</span>
                <span className="text-[11px] text-muted tabular-nums shrink-0">{formatTime(item.created_at)}</span>
                <ItemStatusSelect item={item} onChange={updateStatus} />
            </div>
        );
    };

    /**
     * Daftar menu satu pesanan sebagai tabel mini, dipakai board yang sedang
     * melebar.
     *
     * Di mode tiga kolom baris item masih enak dibaca satu per satu, tapi begitu
     * satu status melebar menjadi satu layar menu satu pesanan jadi terpecah ke
     * beberapa kolom dan sulit dipindai. Tabel mini yang kolomnya lurus membuat
     * tiap pesanan terbaca sebagai satu blok utuh, dan beberapa blok bisa
     * berdampingan supaya ruang kosong tidak terbuang.
     */
    const renderOrderTable = (order, stage) => {
        return (
            <table className="w-full">
                <thead>
                    <tr>
                        <th className="table-head !px-2 w-12 text-center">Qty</th>
                        <th className="table-head !px-2">Menu</th>
                        <th className="table-head !px-2 w-28 text-center">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {order.items.filter((i) => i.status === stage.key).map((item) => (
                        <tr key={item.id} className="hover:bg-surface-2">
                            <td className="table-cell !px-2 text-center font-black text-accent tabular-nums">{item.qty}&times;</td>
                            <td className="table-cell !px-2 font-semibold">{item.product?.name}</td>
                            <td className="table-cell !px-2 text-center">
                                <ItemStatusSelect item={item} onChange={updateStatus} className="mx-auto" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );
    };

    /**
     * Satu blok pesanan di papan.
     *
     * Baris judulnya bisa ditutup per order lewat tombol minus/plus. Pada board
     * tiga kolom judulnya memakai warna netral dan jam pesanan, sedangkan pada
     * board yang melebar judulnya memakai pita warna tahap dan daftar menunya
     * diubah menjadi tabel mini supaya blok pesanan tetap terbaca utuh.
     */
    const renderOrder = (order, stage, wide) => {
        const open = !collapsedKeys[order.id];

        return (
            <div key={order.id} className={wide ? 'bg-surface border border-line break-inside-avoid mb-3' : 'py-2'}>
                <div className={wide ? `flex items-center gap-2 px-3 py-2 text-white ${stage.band}` : 'flex items-center justify-between gap-2'}>
                    <button
                        type="button"
                        onClick={() => toggleCollapsed(order.id)}
                        title={open ? 'Tutup pesanan ini' : 'Buka pesanan ini'}
                        className="flex items-baseline gap-2 min-w-0 text-left cursor-pointer"
                    >
                        <span className="w-3 shrink-0 text-center font-black">{open ? '−' : '+'}</span>
                        <span className="text-sm font-black tracking-wide">{order.order_number}</span>
                        <span className={`text-xs truncate ${wide ? 'opacity-80' : 'text-muted'}`}>
                            Meja {order.table_number ?? '-'}
                        </span>
                    </button>
                    {!wide && (
                        <span className="text-xs text-muted tabular-nums shrink-0">{formatTime(order.created_at)}</span>
                    )}
                </div>

                {open &&
                    (wide ? (
                        renderOrderTable(order, stage)
                    ) : (
                        <div className="mt-1 space-y-1">
                            {order.items.filter((i) => i.status === stage.key).map(renderItemRow)}
                        </div>
                    ))}
            </div>
        );
    };

    const renderGroupedTable = () => {
        if (filteredOrders.length === 0) {
            return (
                <div className="card rounded-none text-muted text-center py-14">
                    {query.trim() ? 'Tidak ada pesanan dengan nomor tersebut.' : 'Tidak ada pesanan.'}
                </div>
            );
        }

        return (
            <div className="border border-line rounded-none overflow-hidden">
                <table className="w-full">
                    <thead className="border-b border-line">
                        <tr>
                            <th className="table-head">Pesanan</th>
                            <th className="table-head text-center">Qty</th>
                            <th className="table-head text-center">Status</th>
                        </tr>
                    </thead>

                    {filteredOrders.map((order) => {
                        const meta = STAGES.find((s) => s.key === orderStage(order)) ?? STAGES[0];
                        const doneCount = order.items.filter((i) => i.status === 'done').length;
                        const open = !collapsedKeys[order.id];

                        return (
                            <tbody key={order.id} className="border-t border-line align-top">
                                {/*
                                 * Baris container tiap pesanan memuat nomor pesanan,
                                 * nomor meja, dan progres. Jumlah sudah tampil per menu
                                 * di bawah, dan waktu tidak relevan di layar dapur.
                                 */}
                                <tr
                                    className={`${meta.band} text-white cursor-pointer select-none`}
                                    onClick={() => toggleCollapsed(order.id)}
                                    title={open ? 'Tutup pesanan' : 'Buka pesanan'}
                                >
                                    <td colSpan={3} className="px-4 py-2.5">
                                        <div className="flex items-center gap-2">
                                            <span className="w-4 text-center font-black">{open ? '−' : '+'}</span>
                                            {/*
                                             * Nomor pesanan membuka detail. Tombol
                                             * terpisah supaya tidak ikut memicu
                                             * buka/tutup baris pesanan.
                                             */}
                                            <button
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setSelectedId(order.id);
                                                }}
                                                title="Lihat detail pesanan"
                                                className="font-black tracking-wide underline underline-offset-2 decoration-2 cursor-pointer hover:opacity-80"
                                            >
                                                {order.order_number}
                                            </button>
                                            <span className="text-white/80 text-[11px] font-semibold whitespace-nowrap">
                                                Meja {order.table_number ?? '-'}
                                            </span>
                                            <span className="text-white/80 text-[11px] font-semibold">
                                                {doneCount}/{order.items.length} selesai
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                {open &&
                                    order.items.map((item) => (
                                        <tr key={item.id} className="border-t border-line hover:bg-accent-soft/30">
                                            <td className="table-cell">
                                                <span className="font-semibold">{item.product?.name}</span>
                                            </td>
                                            <td className="table-cell text-center font-black text-accent">{item.qty}×</td>
                                            <td className="table-cell text-center">
                                                <ItemStatusSelect item={item} onChange={updateStatus} className="mx-auto" />
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        );
                    })}
                </table>
            </div>
        );
    };

    const selectedOrder = orders.find((o) => o.id === selectedId) ?? null;

    const renderDetailModal = () => {
        if (!selectedOrder) return null;
        const meta = STAGES.find((s) => s.key === orderStage(selectedOrder)) ?? STAGES[0];
        const doneCount = selectedOrder.items.filter((i) => i.status === 'done').length;

        return (
            <div
                className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
                onClick={() => setSelectedId(null)}
            >
                <div
                    className="bg-surface rounded-none w-full max-w-lg max-h-[85vh] overflow-y-auto"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className={`flex items-center justify-between px-4 py-2.5 ${meta.band} text-white text-sm font-bold`}>
                        <span>{selectedOrder.order_number}</span>
                        <button
                            onClick={() => setSelectedId(null)}
                            className="text-white/90 hover:text-white cursor-pointer text-xs font-semibold"
                        >
                            Tutup
                        </button>
                    </div>

                    <div className="px-4 py-3 flex items-center justify-between border-b border-line">
                        <div className="text-sm font-semibold">
                            Meja {selectedOrder.table_number ?? '-'}
                            <span className="block text-[11px] text-muted font-normal">{formatTime(selectedOrder.created_at, true)}</span>
                        </div>
                        <span className="text-xs text-muted">{doneCount}/{selectedOrder.items.length} selesai</span>
                    </div>

                    <div className="px-4 pt-3 pb-1">
                        <span className="text-[11px] font-bold text-muted uppercase tracking-wide">Ubah status menu secara manual</span>
                    </div>

                    <div className="p-4 space-y-2">
                        {selectedOrder.items.map((item) => (
                            <div key={item.id} className="flex items-center gap-2 bg-surface-2 rounded-none px-2 py-1.5">
                                <span className="text-accent font-black text-sm shrink-0 tabular-nums">{item.qty}&times;</span>
                                <span className="flex-1 min-w-0 text-sm font-semibold truncate">{item.product?.name}</span>
                                <span className="text-[11px] text-muted tabular-nums shrink-0">{formatTime(item.created_at)}</span>
                                <ItemStatusSelect item={item} onChange={updateStatus} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        );
    };

    const header = {
        navLabel: 'Dapur',
        mode: view,
        onModeChange: setView,
        viewModes: KITCHEN_MODES,
        showCatalog: true,
        query,
        onQueryChange: setQuery,
        searchPlaceholder: 'Cari no pesanan...',
    };

    return (
        <Layout header={header}>
            {isLoading ? (
                <div className="card rounded-none text-muted">Memuat SPK...</div>
            ) : view === 'table' ? (
                renderGroupedTable()
            ) : (
                <div className={focusStage ? '' : 'grid gap-x-4 gap-y-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-4'}>
                    {shownStages.map((stage) => {
                        const focused = focusStage === stage.key;
                        return (
                            <div key={stage.key} className="flex flex-col min-w-0 rounded-none">
                                {/*
                                 * Judul tahap sekaligus tombol perluasan: ditekan
                                 * sekali, kolom ini melebar sendiri memenuhi
                                 * layar dan hanya menampilkan order pada status
                                 * itu. Tekan lagi untuk kembali ke 4 kolom.
                                 */}
                                <button
                                    type="button"
                                    onClick={() => setFocusStage((s) => (s === stage.key ? null : stage.key))}
                                    title={focused ? 'Kembali ke 4 kolom' : `Perlebar ${stage.label}`}
                                    className={`w-full rounded-none px-3 py-2 text-sm font-bold text-white text-left cursor-pointer ${stage.band} ${
                                        focused ? 'ring-2 ring-accent ring-offset-1 ring-offset-page' : ''
                                    }`}
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="truncate">{stage.label}</span>
                                        <span className="shrink-0 tabular-nums">{stage.orders.length}</span>
                                    </span>
                                </button>

                                {/*
                                 * Saat statusnya diperlebar, blok pesanan disusun
                                 * menjadi grid tabel mini agar beberapa pesanan
                                 * bisa dibaca berdampingan, bukan menumpuk satu
                                 * kolom panjang yang harus digulir.
                                 *
                                 * Susunannya pakai multi-column, bukan grid:
                                 * grid menyamakan tinggi tiap baris sehingga
                                 * blok yang ditutup menyisakan rongga kosong di
                                 * bawahnya dan blok berikutnya turun ke baris
                                 * baru. Multi-column mengalir sesuai tinggi
                                 * sebenarnya, jadi blok yang ditutup langsung
                                 * disusul blok di bawahnya.
                                 */}
                                <div
                                    className={
                                        focused
                                            ? 'mt-2 columns-1 md:columns-2 xl:columns-3 gap-3 [&>*:last-child]:mb-0'
                                            : 'mt-2 divide-y divide-line'
                                    }
                                >
                                    {stage.orders.length === 0 ? (
                                        <p className="text-xs text-muted text-center py-6">Kosong</p>
                                    ) : (
                                        stage.orders.map((order) => renderOrder(order, stage, focused))
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {renderDetailModal()}
        </Layout>
    );
}