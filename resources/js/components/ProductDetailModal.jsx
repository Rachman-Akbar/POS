import { useEffect, useState } from 'react';
import { X, Minus, Plus, ShoppingCart, ImageOff } from 'lucide-react';
import { formatIDR } from '../api/client';

function ProductImage({ product, className = '' }) {
    const [err, setErr] = useState(false);
    if (!product.image || err) {
        return (
            <div className={`bg-gray-100 flex items-center justify-center ${className}`}>
                <ImageOff size={28} className="text-gray-300" />
            </div>
        );
    }
    return <img src={product.image} alt={product.name} className={className} onError={() => setErr(true)} />;
}

export default function ProductDetailModal({ product, onClose, onAdd }) {
    const [qty, setQty] = useState(1);

    useEffect(() => {
        setQty(1);
        const handler = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [product, onClose]);

    if (!product) return null;

    const outOfStock = product.stock <= 0;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={onClose}>
            <div
                className="bg-white rounded-2xl w-full max-w-md overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                <ProductImage product={product} className="w-full aspect-[4/3] object-cover" />

                <div className="p-5">
                    <div className="flex items-start justify-between gap-3 mb-1">
                        <div>
                            <span className="text-[11px] font-semibold text-muted uppercase tracking-wide">{product.category ?? 'Lainnya'}</span>
                            <h3 className="text-lg font-bold leading-snug">{product.name}</h3>
                        </div>
                        <button onClick={onClose} className="btn-icon w-9 h-9 text-muted hover:bg-gray-100">
                            <X size={18} />
                        </button>
                    </div>

                    <div className="flex items-center gap-2 mt-2 mb-3">
                        <span className="text-xl font-bold text-orange-600">{formatIDR(product.price)}</span>
                        {product.stock > 0 ? (
                            <span className="badge badge-done">Stok {product.stock}</span>
                        ) : (
                            <span className="badge badge-unpaid">Habis</span>
                        )}
                    </div>

                    {product.description && <p className="text-sm text-muted mb-4">{product.description}</p>}

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1 border border-gray-200 rounded-lg p-1">
                            <button onClick={() => setQty((q) => Math.max(1, q - 1))} disabled={qty <= 1 || outOfStock} className="btn-icon w-8 h-8 hover:bg-gray-100 disabled:opacity-40">
                                <Minus size={14} />
                            </button>
                            <span className="w-8 text-center font-bold">{qty}</span>
                            <button onClick={() => setQty((q) => q + 1)} disabled={outOfStock} className="btn-icon w-8 h-8 bg-orange-100 text-orange-700 hover:bg-orange-200 disabled:opacity-40">
                                <Plus size={14} />
                            </button>
                        </div>
                        <button
                            className="btn btn-success flex-1 justify-center"
                            disabled={outOfStock}
                            onClick={() => {
                                onAdd(product, qty);
                                onClose();
                            }}
                        >
                            <ShoppingCart size={16} /> {outOfStock ? 'Stok Habis' : 'Tambah ke Transaksi'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}