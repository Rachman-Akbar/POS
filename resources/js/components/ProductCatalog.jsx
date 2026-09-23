import { useMemo, useState } from 'react';
import { LayoutGrid, Table as TableIcon, ImageOff } from 'lucide-react';
import { formatIDR } from '../api/client';

export default function ProductCatalog({ products = [], onAdd, compact = false }) {
    const [view, setView] = useState('grid');

    const grouped = useMemo(
        () =>
            products.reduce((acc, p) => {
                (acc[p.category ?? 'Lainnya'] ??= []).push(p);
                return acc;
            }, {}),
        [products],
    );

    const ProductImage = ({ product }) => {
        const [err, setErr] = useState(false);
        if (!product.image || err) {
            return (
                <div className="bg-gray-100 flex items-center justify-center">
                    <ImageOff size={20} className="text-gray-400" />
                </div>
            );
        }
        return (
            <img
                src={product.image}
                alt={product.name}
                loading="lazy"
                onError={() => setErr(true)}
                className="w-full h-full object-cover"
            />
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-end gap-1">
                <button
                    onClick={() => setView('grid')}
                    title="Tampilan Kartu"
                    className={`btn-icon w-8 h-8 !rounded-lg ${view === 'grid' ? 'bg-orange-600 text-white' : 'bg-white border border-gray-200 text-gray-500'}`}
                >
                    <LayoutGrid size={15} />
                </button>
                <button
                    onClick={() => setView('table')}
                    title="Tampilan Tabel"
                    className={`btn-icon w-8 h-8 !rounded-lg ${view === 'table' ? 'bg-orange-600 text-white' : 'bg-white border border-gray-200 text-gray-500'}`}
                >
                    <TableIcon size={15} />
                </button>
            </div>

            {Object.entries(grouped).map(([category, items]) => (
                <div key={category}>
                    <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500 mb-2">{category}</h2>

                    {view === 'grid' ? (
                        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                            {items.map((product) => (
                                <button
                                    key={product.id}
                                    onClick={() => product.stock > 0 && onAdd(product)}
                                    disabled={product.stock <= 0}
                                    className="card text-left hover:border-orange-400 transition-colors disabled:opacity-40 cursor-pointer !p-3"
                                >
                                    <div className="aspect-square w-full overflow-hidden rounded-xl mb-2 bg-gray-100">
                                        <ProductImage product={product} />
                                    </div>
                                    <div className="font-semibold leading-snug text-sm line-clamp-1">{product.name}</div>
                                    <div className="mt-1 text-orange-600 font-bold text-sm">{formatIDR(product.price)}</div>
                                    <div className="text-[11px] text-muted mt-1">Stok {product.stock}</div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <div className="card !p-0 overflow-hidden">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="table-head">Produk</th>
                                        <th className="table-head text-right">Harga</th>
                                        <th className="table-head text-center">Stok</th>
                                        <th className="table-head text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {items.map((product) => (
                                        <tr key={product.id}>
                                            <td className="table-cell">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-gray-100">
                                                        <ProductImage product={product} />
                                                    </div>
                                                    <span className="font-semibold">{product.name}</span>
                                                </div>
                                            </td>
                                            <td className="table-cell text-right text-orange-600 font-semibold">{formatIDR(product.price)}</td>
                                            <td className="table-cell text-center">{product.stock}</td>
                                            <td className="table-cell text-right">
                                                <button onClick={() => product.stock > 0 && onAdd(product)} disabled={product.stock <= 0} className="btn-secondary btn !px-3 !py-1.5 text-xs">
                                                    + Tambah
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}