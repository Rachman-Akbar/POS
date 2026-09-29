import { useState } from 'react';
import { ChevronLeft, ChevronRight, ImageOff, Minus, Plus, Trash2 } from 'lucide-react';
import { formatIDR } from '../../api/client';
import { PriceRow } from '../Price';
import { ItemStatusBadge } from '../badges';

/**
 * Daftar baris produk untuk layar kasir.
 *
 * Satu komponen dipakai dua kali: sekali di tab "Cek Pesanan" (keranjang
 * aktif) dan sekali di "Detail Pesanan" (order tersimpan). Keduanya memakai
 * kerangka, grid, dan kartu yang sama supaya tampilannya tidak berbeda; yang
 * berbeda hanya kendali ubah jumlah/hapus, yang dimatikan saat `readOnly`.
 *
 * @typedef {object} Line
 * @property {number|string} product_id Kunci baris.
 * @property {string} name Nama produk.
 * @property {number} price Harga satuan.
 * @property {number} qty Jumlah.
 * @property {string} [image] URL foto, dipakai bila produk katalog tidak ada.
 * @property {object} [product] Baris produk dari katalog (foto, stok).
 * @property {string} [note] Catatan item.
 * @property {string} [status] Status item, hanya untuk `readOnly`.
 */

export function QtyStepper({ qty, onChange, size = 'sm' }) {
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

export function LineThumb({ product, className = 'w-10 h-10 rounded-lg' }) {
    if (!product?.image) {
        return (
            <div className={`${className} bg-surface-2 flex items-center justify-center shrink-0`}>
                <ImageOff size={16} className="text-faint" />
            </div>
        );
    }
    return <img src={product.image} alt={product.name} loading="lazy" className={`${className} object-cover shrink-0`} />;
}

/**
 * Daftar produk dengan tampilan mengikuti mode tampilan yang aktif.
 *
 * @param {object} props
 * @param {'grid'|'table'|'hero'} props.view Mode tampilan aktif.
 * @param {Line[]} props.lines Baris produk.
 * @param {(id: number|string, delta: number) => void} [props.updateQty] Ubah jumlah.
 * @param {(id: number|string) => void} [props.removeLine] Hapus baris.
 * @param {boolean} [props.readOnly] Tampilkan apa adanya tanpa kendali ubah/hapus.
 */
export default function ProductLinesList({ view = 'grid', lines, updateQty, removeLine, readOnly = false }) {
    if (lines.length === 0) {
        return <p className="text-muted text-sm text-center py-10">Belum ada produk.</p>;
    }

    if (view === 'table') {
        return <LinesTable lines={lines} updateQty={updateQty} removeLine={removeLine} readOnly={readOnly} />;
    }

    if (view === 'hero') {
        return <LinesHero lines={lines} updateQty={updateQty} removeLine={removeLine} readOnly={readOnly} />;
    }

    return (
        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
            {lines.map((line) => (
                <LineCard
                    key={line.product_id}
                    line={line}
                    updateQty={updateQty}
                    removeLine={removeLine}
                    readOnly={readOnly}
                />
            ))}
        </div>
    );
}

/**
 * Kartu produk untuk mode grid, kerangkanya sama dengan kartu katalog.
 */
function LineCard({ line, updateQty, removeLine, readOnly }) {
    return (
        <div className="flex flex-col rounded-xl overflow-hidden bg-surface-2">
            <div className="relative w-full aspect-square overflow-hidden bg-surface-3 group">
                <LineThumb
                    product={line.product ?? (line.image ? { image: line.image, name: line.name } : null)}
                    className="absolute inset-0 w-full h-full object-cover"
                />
                {readOnly && line.status && (
                    <div className="absolute top-2 right-2">
                        <ItemStatusBadge status={line.status} />
                    </div>
                )}
                {!readOnly && (
                    <button
                        onClick={() => removeLine(line.product_id)}
                        title="Hapus item"
                        className="absolute top-2 right-2 w-8 h-8 rounded-full bg-black/55 text-white flex items-center justify-center backdrop-blur-sm transition-colors hover:bg-negative focus-visible:bg-negative"
                    >
                        <Trash2 size={15} />
                    </button>
                )}
            </div>

            <div className="flex-1 flex flex-col gap-2 p-3">
                <div className="font-semibold text-sm leading-snug line-clamp-2">{line.name}</div>
                {line.note && <div className="text-[11px] text-muted italic truncate">{line.note}</div>}

                <div className="mt-auto flex items-end justify-between gap-2">
                    {readOnly ? (
                        <span className="text-sm font-bold text-muted tabular-nums">{line.qty} item</span>
                    ) : (
                        <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                    )}

                    <div className="text-right shrink-0">
                        <PriceRow
                            value={line.price * line.qty}
                            className="text-sm"
                            amountClassName="text-accent font-bold"
                        />
                        <div className="text-[11px] text-muted whitespace-nowrap tabular-nums">
                            {formatUnit(line.price)} &times; {line.qty}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function LinesTable({ lines, updateQty, removeLine, readOnly }) {
    return (
        <div className="border border-line rounded-xl overflow-hidden bg-surface">
            <table className="w-full">
                <thead className="border-b border-line">
                    <tr>
                        <th className="table-head">Produk</th>
                        <th className="table-head text-right">Harga</th>
                        <th className="table-head text-center">Qty</th>
                        <th className="table-head text-right">Subtotal</th>
                        {readOnly && <th className="table-head text-center">Status</th>}
                        {!readOnly && <th className="table-head text-center w-14"></th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {lines.map((line) => (
                        <tr key={line.product_id}>
                            <td className="table-cell font-semibold">{line.name}</td>
                            <td className="table-cell text-right">{formatUnit(line.price)}</td>
                            <td className="table-cell text-center">
                                {readOnly ? (
                                    <span className="tabular-nums font-semibold">{line.qty}</span>
                                ) : (
                                    <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                                )}
                            </td>
                            <td className="table-cell text-right font-semibold text-accent">{formatUnit(line.price * line.qty)}</td>
                            {readOnly && (
                                <td className="table-cell text-center">
                                    {line.status ? <ItemStatusBadge status={line.status} /> : '-'}
                                </td>
                            )}
                            {!readOnly && (
                                <td className="table-cell text-center">
                                    <button onClick={() => removeLine(line.product_id)} className="text-negative hover:text-red-700 dark:hover:text-red-300 p-1" title="Hapus item">
                                        <Trash2 size={15} />
                                    </button>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function LinesHero({ lines, updateQty, removeLine, readOnly }) {
    const [active, setActive] = useState(0);
    const index = Math.min(active, lines.length - 1);
    const line = lines[index];
    const product = line?.product ?? (line?.image ? { image: line.image, name: line.name } : null);

    if (!line) return null;

    const go = (delta) => setActive((a) => Math.min(lines.length - 1, Math.max(0, a + delta)));

    return (
        <div className="rounded-xl overflow-hidden" style={{ height: 'calc(100dvh - 15rem)' }}>
            <div className="relative h-full flex flex-col">
                {product?.image ? (
                    <img src={product.image} alt={line.name} className="absolute inset-0 w-full h-full object-cover" />
                ) : (
                    <div className="absolute inset-0 bg-surface-2" />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

                {readOnly && line.status && (
                    <div className="absolute top-3 right-3">
                        <ItemStatusBadge status={line.status} />
                    </div>
                )}

                {!readOnly && (
                    <button
                        onClick={() => removeLine(line.product_id)}
                        title="Hapus item"
                        className="absolute top-3 right-3 w-9 h-9 rounded-full bg-black/55 text-white flex items-center justify-center backdrop-blur-sm transition-colors hover:bg-negative focus-visible:bg-negative"
                    >
                        <Trash2 size={16} />
                    </button>
                )}

                {lines.length > 1 && (
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
                            disabled={index === lines.length - 1}
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 text-gray-700 flex items-center justify-center hover:bg-white disabled:opacity-40"
                        >
                            <ChevronRight size={18} />
                        </button>
                    </>
                )}

                <div className="relative mt-auto p-5 text-white flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <div className="text-lg font-bold">{line.name}</div>
                        <div className="text-sm opacity-80 tabular-nums">
                            {formatUnit(line.price)} &times; {line.qty}
                        </div>
                    </div>
                    <div className="text-right shrink-0">
                        <div className="text-2xl font-bold tabular-nums">
                            {formatUnit(line.price * line.qty)}
                        </div>
                        {!readOnly && (
                            <div className="mt-2 flex justify-end">
                                <QtyStepper qty={line.qty} onChange={(delta) => updateQty(line.product_id, delta)} />
                            </div>
                        )}
                    </div>
                </div>

                {lines.length > 1 && (
                    <div className="relative flex items-center gap-2 overflow-x-auto scrollbar-thin px-4 pb-4">
                        {lines.map((item, i) => (
                            <button
                                key={item.product_id}
                                onClick={() => setActive(i)}
                                aria-label={`Produk ${i + 1}: ${item.name}`}
                                title={item.name}
                                className={`shrink-0 w-12 h-12 rounded-lg overflow-hidden transition-all ${
                                    i === index
                                        ? 'ring-2 ring-white opacity-100'
                                        : 'opacity-60 hover:opacity-100'
                                }`}
                            >
                                <LineThumb
                                    product={item.product ?? (item.image ? { image: item.image, name: item.name } : null)}
                                    className="w-full h-full"
                                />
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Harga satuan untuk baris tabel dan kartu.
 */
function formatUnit(value) {
    return formatIDR(value);
}
