import { useEffect, useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Clock, Flame, CookingPot, CheckCheck, BellRing, ListOrdered, ArrowRight } from 'lucide-react';
import Layout from '../components/Layout';
import { api } from '../api/client';
import { listenToOrders } from '../realtime/echo';
import { notifyError } from '../utils/alerts';

const ITEM_STATUS_META = {
    pending: { icon: Flame, label: 'Mulai Masak', next: 'cooking', btn: 'btn-warning' },
    cooking: { icon: CheckCheck, label: 'Tandai Selesai', next: 'done', btn: 'btn-success' },
    done: { icon: CheckCheck, label: 'Selesai', next: null, btn: '' },
};

const ORDER_PROGRESS = (items) => {
    const statuses = items.map((i) => i.status);
    if (statuses.some((s) => s === 'done')) return 'done';
    if (statuses.some((s) => s === 'cooking')) return 'cooking';
    return 'pending';
};

const ORDER_META = {
    pending: { label: 'Menunggu dapur', band: 'bg-gray-600' },
    cooking: { label: 'Sedang dimasak', band: 'bg-amber-500' },
    done: { label: 'Siap antar', band: 'bg-emerald-500' },
};

export default function KitchenDashboard() {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState('pending');

    const { data: items = { waiting: [], cooking: [], done: [] }, isLoading } = useQuery({
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
        [...items.waiting, ...items.cooking, ...items.done].forEach((item) => {
            const order = item.order;
            if (!grouped.has(order.id)) {
                grouped.set(order.id, { ...order, items: [], created_at: order.created_at });
            }
            grouped.get(order.id).items.push(item);
        });
        return [...grouped.values()].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    }, [items]);

    const totalItems = orders.reduce((sum, o) => sum + o.items.length, 0);

    const tabCounts = {
        pending: orders.filter((o) => ORDER_PROGRESS(o.items) === 'pending').length,
        cooking: orders.filter((o) => ORDER_PROGRESS(o.items) === 'cooking').length,
        done: orders.filter((o) => ORDER_PROGRESS(o.items) === 'done').length,
    };

    const visibleOrders = orders.filter((o) => ORDER_PROGRESS(o.items) === tab);

    const advance = async (item, status) => {
        queryClient.setQueryData(['kitchen-items'], (old) => {
            if (!old) return old;
            const updated = { ...old, waiting: [...old.waiting], cooking: [...old.cooking], done: [...old.done] };
            Object.keys(updated).forEach((group) => {
                updated[group] = updated[group].map((i) => (i.id === item.id ? { ...i, status } : i));
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

    const renderItemRow = (item) => {
        const meta = ITEM_STATUS_META[item.status];
        return (
            <div key={item.id} className="flex items-center justify-between gap-3 bg-gray-50 rounded-xl px-3 py-2">
                <div className="flex-1 min-w-0">
                    <div className="font-semibold text-sm truncate">{item.product?.name}</div>
                    <div className="text-xs text-muted flex items-center gap-1">
                        <Clock size={11} /> {new Date(item.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                    </div>
                </div>

                <span className="badge badge-orange-600 shrink-0">
                    <span className="text-orange-600 font-black text-base">{item.qty}×</span>
                </span>

                {meta.next ? (
                    <button onClick={() => advance(item, meta.next)} className={`btn ${meta.btn} !px-3 !py-1.5 shrink-0`}>
                        <meta.icon size={15} /> {meta.label}
                    </button>
                ) : (
                    <span className="inline-flex items-center gap-1 text-emerald-700 text-sm font-bold shrink-0">
                        <CheckCheck size={15} /> Selesai
                    </span>
                )}
            </div>
        );
    };

    const renderOrder = (order) => {
        const progress = ORDER_PROGRESS(order.items);
        const meta = ORDER_META[progress];
        return (
            <div key={order.id} className="card !p-0 overflow-hidden border-t-4 border-t-transparent border-gray-200/80">
                <div className={`flex items-center justify-between px-4 py-2 ${meta.band} text-white text-xs font-bold rounded-t-2xl`}>
                    <span className="flex items-center gap-1.5">
                        <ListOrdered size={13} /> {order.order_number}
                    </span>
                    <span>Meja {order.table_number ?? '-'}</span>
                </div>

                <div className="p-4 space-y-2">
                    {order.items.map(renderItemRow)}
                </div>

                <div className="flex items-center justify-between px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 rounded-b-2xl">
                    <span className="text-xs text-muted font-semibold">{meta.label}</span>
                    <span className="text-xs text-muted">
                        {order.items.filter((i) => i.status === 'done').length}/{order.items.length} selesai
                    </span>
                </div>
            </div>
        );
    };

    return (
        <Layout
            title="Kitchen Display"
            subtitle="SPK pesanan dapur real-time"
            right={
                <span className="badge badge-cooking">
                    <BellRing size={14} /> {orders.length} pesanan · {totalItems} item
                </span>
            }
        >
            <div className="flex items-center gap-2 mb-6 flex-wrap">
                {[
                    { key: 'pending', label: 'Menunggu', icon: Clock },
                    { key: 'cooking', label: 'Diproses', icon: CookingPot },
                    { key: 'done', label: 'Selesai', icon: CheckCheck },
                ].map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={`btn border text-sm ${tab === t.key ? 'bg-orange-600 text-white border-orange-600' : 'border-gray-300 text-gray-600'}`}
                    >
                        <t.icon size={15} /> {t.label}
                        <span className={`badge ml-1 ${tab === t.key ? 'bg-white/20 text-white' : 'badge-pending'}`}>{tabCounts[t.key]}</span>
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="card text-muted">Memuat SPK...</div>
            ) : visibleOrders.length === 0 ? (
                <div className="card text-muted text-center py-14">Tidak ada pesanan pada papan ini.</div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
                    {visibleOrders.map(renderOrder)}
                </div>
            )}

            <div className="mt-6 flex items-center gap-2 text-muted text-sm">
                <ArrowRight size={16} /> Ubah status menu satu per satu di dalam kartu pesanan: Menunggu → Diproses → Selesai.
            </div>
        </Layout>
    );
}