import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Clock, CookingPot, CheckCheck, ListOrdered, ArrowRight, Table2, LayoutGrid, ChevronRight, ChevronDown, X, Send, Folder, FolderOpen, FileText } from 'lucide-react';
import Layout from '../components/Layout';
import { api } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifyError } from '../utils/alerts';
import { ItemStatusBadge, ITEM_STATUS } from '../components/badges';

const STAGES = [
    { key: 'pending', label: 'Dipesan', band: 'bg-gray-600', icon: Clock },
    { key: 'cooking', label: 'Diproses', band: 'bg-amber-500', icon: CookingPot },
    { key: 'sent', label: 'Dikirim', band: 'bg-blue-600', icon: Send },
    { key: 'done', label: 'Selesai', band: 'bg-emerald-500', icon: CheckCheck },
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

export default function KitchenDashboard() {
    const queryClient = useQueryClient();
    const [query, setQuery] = useState('');
    const [view, setView] = useState('board');
    const [selectedId, setSelectedId] = useState(null);
    const [collapsedKeys, setCollapsedKeys] = useState({});

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
            <div key={item.id} className="flex items-center justify-between gap-3 bg-gray-50 rounded-xl px-3 py-2">
                <div className="flex-1 min-w-0">
                    <div className="font-semibold text-sm truncate">{item.product?.name}</div>
                    <div className="text-xs text-muted flex items-center gap-1">
                        <Clock size={11} /> {formatTime(item.created_at)}
                    </div>
                </div>

                <span className="badge badge-orange-600 shrink-0">
                    <span className="text-orange-600 font-black text-base">{item.qty}×</span>
                </span>

                <ItemStatusBadge status={item.status} />
            </div>
        );
    };

    const renderOrder = (order, stage) => {
        const doneCount = order.items.filter((i) => i.status === 'done').length;
        return (
            <div key={order.id} className="card !p-0 overflow-hidden border-t-4 border-t-transparent border-gray-200/80">
                <div
                    role="button"
                    tabIndex="0"
                    onClick={() => setSelectedId(order.id)}
                    onKeyDown={(e) => e.key === 'Enter' && setSelectedId(order.id)}
                    className={`w-full flex items-center justify-between px-4 py-2 ${stage.band} text-white text-xs font-bold rounded-t-2xl cursor-pointer`}
                    title="Lihat detail pesanan"
                >
                    <span className="flex items-center gap-1.5">
                        <ListOrdered size={13} /> {order.order_number}
                    </span>
                    <span>Meja {order.table_number ?? '-'}</span>
                </div>

                <div className="p-4 space-y-2">
                    {order.items.filter((i) => i.status === stage.key).map(renderItemRow)}
                </div>

                <div className="flex items-center justify-between px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 rounded-b-2xl">
                    <span className="text-xs text-muted font-semibold">
                        {stage.label} · {doneCount}/{order.items.length} selesai
                    </span>
                    <button
                        type="button"
                        onClick={() => setSelectedId(order.id)}
                        className="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:underline cursor-pointer"
                    >
                        Detail <ArrowRight size={12} />
                    </button>
                </div>
            </div>
        );
    };

    const renderGroupedTable = () => {
        if (filteredOrders.length === 0) {
            return (
                <div className="card text-muted text-center py-14">
                    {query.trim() ? 'Tidak ada pesanan dengan nomor tersebut.' : 'Tidak ada pesanan.'}
                </div>
            );
        }

        return (
            <div className="border border-gray-200/80 rounded-2xl overflow-hidden bg-white shadow-sm">
                <table className="w-full">
                    <thead className="bg-gray-100/80">
                        <tr>
                            <th className="table-head">Pesanan</th>
                            <th className="table-head text-center">Qty</th>
                            <th className="table-head text-center">Meja</th>
                            <th className="table-head text-center">Waktu</th>
                            <th className="table-head text-center">Status</th>
                            <th className="table-head text-right">Aksi</th>
                        </tr>
                    </thead>

                    {filteredOrders.map((order) => {
                        const meta = STAGES.find((s) => s.key === orderStage(order)) ?? STAGES[0];
                        const doneCount = order.items.filter((i) => i.status === 'done').length;
                        const totalQty = order.items.reduce((sum, i) => sum + i.qty, 0);
                        const open = !collapsedKeys[order.id];
                        const FolderIcon = open ? FolderOpen : Folder;

                        return (
                            <tbody key={order.id} className="border-t border-gray-100 align-top">
                                <tr
                                    className={`${meta.band} text-white cursor-pointer select-none`}
                                    onClick={() => toggleCollapsed(order.id)}
                                    title={open ? 'Tutup folder pesanan' : 'Buka folder pesanan'}
                                >
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-center gap-2">
                                            {open ? <ChevronDown size={15} className="shrink-0" /> : <ChevronRight size={15} className="shrink-0" />}
                                            <FolderIcon size={15} className="shrink-0" />
                                            <span className="font-black tracking-wide">{order.order_number}</span>
                                            <span className="text-white/80 text-[11px] font-semibold">
                                                {doneCount}/{order.items.length} selesai
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5 text-center font-bold">{totalQty}</td>
                                    <td className="px-4 py-2.5 text-center whitespace-nowrap">Meja {order.table_number ?? '-'}</td>
                                    <td className="px-4 py-2.5 text-center whitespace-nowrap">{formatTime(order.created_at, true)}</td>
                                    <td className="px-4 py-2.5 text-center">
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-bold">
                                            <meta.icon size={12} /> {meta.label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setSelectedId(order.id);
                                            }}
                                            className="rounded-lg bg-white/20 hover:bg-white/30 px-2.5 py-1 text-xs font-semibold cursor-pointer transition-colors"
                                        >
                                            Detail
                                        </button>
                                    </td>
                                </tr>

                                {open &&
                                    order.items.map((item) => (
                                        <tr key={item.id} className="border-t border-gray-50 hover:bg-orange-50/30">
                                            <td className="table-cell">
                                                <div className="flex items-center gap-2 pl-7">
                                                    <FileText size={13} className="text-gray-300 shrink-0" />
                                                    <span className="font-semibold">{item.product?.name}</span>
                                                </div>
                                            </td>
                                            <td className="table-cell text-center font-black text-orange-600">{item.qty}×</td>
                                            <td className="table-cell text-center text-xs text-muted">-</td>
                                            <td className="table-cell text-center text-xs text-muted whitespace-nowrap">{formatTime(item.created_at)}</td>
                                            <td className="table-cell text-center">
                                                <ItemStatusBadge status={item.status} />
                                            </td>
                                            <td className="table-cell text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedId(order.id)}
                                                    className="text-xs font-bold text-orange-600 hover:underline cursor-pointer"
                                                >
                                                    Detail
                                                </button>
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
                    className="bg-white rounded-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className={`flex items-center justify-between px-4 py-2.5 ${meta.band} text-white text-sm font-bold`}>
                        <span className="flex items-center gap-1.5">
                            <ListOrdered size={14} /> {selectedOrder.order_number}
                        </span>
                        <button onClick={() => setSelectedId(null)} className="text-white/90 hover:text-white cursor-pointer">
                            <X size={18} />
                        </button>
                    </div>

                    <div className="px-4 py-3 flex items-center justify-between border-b border-gray-100">
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
                            <div key={item.id} className="flex items-center justify-between gap-3 bg-gray-50 rounded-xl px-3 py-2">
                                <div className="flex-1 min-w-0">
                                    <div className="font-semibold text-sm truncate">{item.product?.name}</div>
                                    <div className="text-xs text-muted flex items-center gap-2">
                                        <span className="flex items-center gap-1">
                                            <Clock size={11} /> {formatTime(item.created_at)}
                                        </span>
                                        <span className="text-orange-600 font-black">{item.qty}×</span>
                                    </div>
                                </div>

                                <select
                                    value={item.status}
                                    onChange={(e) => updateStatus(item, e.target.value)}
                                    className="input !py-2 !px-2 text-sm font-semibold shrink-0"
                                    title="Ubah status secara manual"
                                >
                                    {Object.entries(ITEM_STATUS).map(([key, meta]) => (
                                        <option key={key} value={key}>{meta.label}</option>
                                    ))}
                                </select>
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
                <div className="card text-muted">Memuat SPK...</div>
            ) : view === 'table' ? (
                renderGroupedTable()
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    {boardGroups.map((stage) => (
                        <div key={stage.key} className="rounded-2xl border border-gray-200/80 bg-gray-50/70 p-3 max-h-[calc(100vh-9rem)] flex flex-col">
                            <div className={`flex items-center justify-between px-3 py-2 rounded-xl ${stage.band} text-white text-xs font-bold mb-3 shrink-0`}>
                                <span className="flex items-center gap-1.5">
                                    <stage.icon size={14} /> {stage.label}
                                </span>
                                <span>{stage.orders.length}</span>
                            </div>
                            <div className="overflow-y-auto scrollbar-thin pr-1 -mr-1 flex-1">
                                {stage.orders.length === 0 ? (
                                    <p className="text-xs text-muted text-center py-8">Kosong</p>
                                ) : (
                                    <div className="space-y-3">
                                        {stage.orders.map((order) => renderOrder(order, stage))}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="mt-6 flex items-center gap-2 text-muted text-sm">
                <ArrowRight size={16} /> Klik Detail pada pesanan untuk mengubah status menu secara manual (Dipesan → Diproses → Dikirim → Selesai).
            </div>

            {renderDetailModal()}
        </Layout>
    );
}