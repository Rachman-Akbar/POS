import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    ChevronLeft, ChevronRight, Package, Pencil, Plus, Search, Star, Trash2,
} from 'lucide-react';
import { api, formatIDR } from '../../api/client';
import { confirmAction, notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field, FieldRow } from './FormModal';

const EMPTY_FORM = {
    name: '',
    sku: '',
    category: '',
    price: '',
    cost_price: '',
    stock: '',
    image: '',
    description: '',
    is_favorite: false,
    is_active: true,
};

const toForm = (product) =>
    product
        ? {
              id: product.id,
              name: product.name,
              sku: product.sku ?? '',
              category: product.category ?? '',
              price: String(product.price ?? ''),
              cost_price: String(product.cost_price ?? ''),
              stock: String(product.stock ?? ''),
              image: product.image ?? '',
              description: product.description ?? '',
              is_favorite: Boolean(product.is_favorite),
              is_active: Boolean(product.is_active),
          }
        : EMPTY_FORM;

export default function ProductManager() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [page, setPage] = useState(1);
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});

    const { data: categories = [] } = useQuery({
        queryKey: ['master-categories'],
        queryFn: async () => (await api.get('/admin/categories')).data.data,
    });

    const { data: response, isLoading } = useQuery({
        queryKey: ['master-products', search, category, page],
        queryFn: async () =>
            (await api.get('/admin/products', { params: { search: search || undefined, category: category || undefined, page } })).data,
    });

    const products = response?.data ?? [];
    const meta = response?.meta ?? { total: 0, current_page: 1, last_page: 1 };

    const refresh = () => {
        queryClient.invalidateQueries({ queryKey: ['master-products'] });
        queryClient.invalidateQueries({ queryKey: ['master-categories'] });
        queryClient.invalidateQueries({ queryKey: ['admin-products'] });
        queryClient.invalidateQueries({ queryKey: ['settings'] });
    };

    const saveProduct = useMutation({
        mutationFn: (payload) =>
            payload.id ? api.put(`/admin/products/${payload.id}`, payload) : api.post('/admin/products', payload),
        onSuccess: () => {
            refresh();
            setForm(null);
            notifySuccess('Produk tersimpan.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Produk tidak dapat disimpan.');
        },
    });

    const removeProduct = async (product) => {
        const confirmed = await confirmAction(
            `Hapus produk "${product.name}"?`,
            'Produk yang sudah dipakai transaksi tidak bisa dihapus, nonaktifkan saja.',
            'Ya, hapus',
        );

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/admin/products/${product.id}`);
            refresh();
            notifySuccess('Produk dihapus.');
        } catch (error) {
            notifyError('Gagal', error.response?.data?.message ?? 'Produk tidak dapat dihapus.');
        }
    };

    const openForm = (product = null) => {
        setErrors({});
        setForm(toForm(product));
    };

    const submitForm = () => {
        saveProduct.mutate({
            ...form,
            name: form.name.trim(),
            sku: form.sku.trim(),
            category: form.category.trim(),
            image: form.image.trim(),
            description: form.description.trim(),
            price: Number(form.price) || 0,
            cost_price: form.cost_price === '' ? null : Number(form.cost_price) || 0,
            stock: Number(form.stock) || 0,
        });
    };

    return (
        <div className="space-y-4">
            <div className="card">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 className="font-bold flex items-center gap-2"><Package size={17} /> Master Produk</h3>
                        <p className="text-sm text-muted mt-0.5">Produk aktif akan langsung tampil di katalog kasir.</p>
                    </div>
                    <button className="btn btn-primary" onClick={() => openForm()}>
                        <Plus size={16} /> Tambah Produk
                    </button>
                </div>

                <div className="flex flex-wrap items-center gap-2 mb-4">
                    <div className="relative flex-1 min-w-[200px] max-w-sm">
                        <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            className="input !pl-9"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setPage(1);
                            }}
                            placeholder="Cari nama, SKU, atau deskripsi..."
                        />
                    </div>
                    <select
                        className="select !w-auto min-w-[160px]"
                        value={category}
                        onChange={(event) => {
                            setCategory(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">Semua kategori</option>
                        {categories.map((item) => (
                            <option key={item.name} value={item.name}>{item.name}</option>
                        ))}
                    </select>
                </div>

                {isLoading ? (
                    <p className="text-muted text-sm text-center py-8">Memuat produk...</p>
                ) : products.length === 0 ? (
                    <p className="text-muted text-sm text-center py-8">Belum ada produk yang cocok.</p>
                ) : (
                    <div className="border border-line rounded-xl overflow-hidden bg-surface">
                        <table className="w-full">
                            <thead className="border-b border-line">
                                <tr>
                                    <th className="table-head">Produk</th>
                                    <th className="table-head">Kategori</th>
                                    <th className="table-head text-right">Harga</th>
                                    <th className="table-head text-right">Stok</th>
                                    <th className="table-head text-center">Status</th>
                                    <th className="table-head text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {products.map((product) => (
                                    <tr key={product.id}>
                                        <td className="table-cell">
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                <div className="w-9 h-9 rounded-lg overflow-hidden shrink-0 bg-surface-2">
                                                    {product.image ? (
                                                        <img src={product.image} alt={product.name} className="w-full h-full object-cover" />
                                                    ) : (
                                                        <Package size={16} className="w-full h-full m-auto text-faint" />
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="font-semibold text-sm truncate flex items-center gap-1.5">
                                                        {product.name}
                                                        {product.is_favorite && <Star size={12} className="text-accent shrink-0" fill="currentColor" />}
                                                    </div>
                                                    <div className="text-[11px] text-muted truncate">{product.sku || 'Tanpa SKU'}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="table-cell text-sm text-muted">{product.category ?? 'Lainnya'}</td>
                                        <td className="table-cell text-right text-accent font-semibold whitespace-nowrap">{formatIDR(product.price)}</td>
                                        <td className="table-cell text-right text-sm whitespace-nowrap">{Number(product.stock) || 0}</td>
                                        <td className="table-cell text-center">
                                            {product.is_active ? (
                                                <span className="badge badge-done">Aktif</span>
                                            ) : (
                                                <span className="badge badge-pending">Nonaktif</span>
                                            )}
                                        </td>
                                        <td className="table-cell">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    className="btn-icon w-8 h-8 text-muted hover:bg-surface-3"
                                                    onClick={() => openForm(product)}
                                                    title="Edit"
                                                >
                                                    <Pencil size={15} />
                                                </button>
                                                <button
                                                    className="btn-icon w-8 h-8 text-negative hover:bg-surface-3"
                                                    onClick={() => removeProduct(product)}
                                                    title="Hapus"
                                                >
                                                    <Trash2 size={15} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex items-center justify-between mt-3">
                    <span className="text-xs text-muted">{meta.total} produk</span>
                    <div className="flex items-center gap-1">
                        <button
                            className="btn btn-ghost !px-2.5 !py-1.5"
                            onClick={() => setPage((current) => Math.max(1, current - 1))}
                            disabled={meta.current_page <= 1}
                        >
                            <ChevronLeft size={15} />
                        </button>
                        <span className="text-xs text-muted px-1">
                            {meta.current_page} / {meta.last_page}
                        </span>
                        <button
                            className="btn btn-ghost !px-2.5 !py-1.5"
                            onClick={() => setPage((current) => Math.min(meta.last_page, current + 1))}
                            disabled={meta.current_page >= meta.last_page}
                        >
                            <ChevronRight size={15} />
                        </button>
                    </div>
                </div>
            </div>

            {form && (
                <FormModal
                    title={form.id ? 'Edit Produk' : 'Tambah Produk'}
                    subtitle="Kategori yang diisi otomatis terdaftar sebagai master kategori."
                    width="max-w-2xl"
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    pending={saveProduct.isPending}
                    disabled={!form.name.trim() || !form.price || Number(form.stock) < 0}
                >
                    <div className="space-y-3">
                        <Field label="Nama Produk" error={errors.name}>
                            <input
                                className="input"
                                value={form.name}
                                onChange={(event) => setForm({ ...form, name: event.target.value })}
                                placeholder="mis. Nasi Goreng Spesial"
                                autoFocus
                            />
                        </Field>

                        <FieldRow>
                            <Field label="SKU" error={errors.sku} hint="Kosongkan bila produk tidak punya SKU.">
                                <input
                                    className="input"
                                    value={form.sku}
                                    onChange={(event) => setForm({ ...form, sku: event.target.value.toUpperCase() })}
                                    placeholder="mis. MKN-001"
                                />
                            </Field>
                            <Field label="Kategori" error={errors.category} hint="Pilih kategori terdaftar atau ketik kategori baru.">
                                <input
                                    className="input"
                                    list="master-category-options"
                                    value={form.category}
                                    onChange={(event) => setForm({ ...form, category: event.target.value })}
                                    placeholder="mis. Makanan"
                                />
                                <datalist id="master-category-options">
                                    {categories.map((item) => (
                                        <option key={item.name} value={item.name} />
                                    ))}
                                </datalist>
                            </Field>
                        </FieldRow>

                        <FieldRow>
                            <Field label="Harga Jual" error={errors.price}>
                                <input
                                    type="number"
                                    min="0"
                                    step="100"
                                    className="input"
                                    value={form.price}
                                    onChange={(event) => setForm({ ...form, price: event.target.value })}
                                    placeholder="0"
                                />
                            </Field>
                            <Field label="Harga Modal" error={errors.cost_price} hint="Opsional, dipakai untuk laporan laba.">
                                <input
                                    type="number"
                                    min="0"
                                    step="100"
                                    className="input"
                                    value={form.cost_price}
                                    onChange={(event) => setForm({ ...form, cost_price: event.target.value })}
                                    placeholder="0"
                                />
                            </Field>
                        </FieldRow>

                        <FieldRow>
                            <Field label="Stok" error={errors.stock}>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    className="input"
                                    value={form.stock}
                                    onChange={(event) => setForm({ ...form, stock: event.target.value })}
                                    placeholder="0"
                                />
                            </Field>
                            <Field label="Link Gambar" error={errors.image} hint="Isi URL gambar produk (opsional).">
                                <input
                                    className="input"
                                    value={form.image}
                                    onChange={(event) => setForm({ ...form, image: event.target.value })}
                                    placeholder="https://.../nasi-goreng.jpg"
                                />
                            </Field>
                        </FieldRow>

                        <Field label="Deskripsi" error={errors.description}>
                            <textarea
                                className="input min-h-[70px] resize-y"
                                value={form.description}
                                onChange={(event) => setForm({ ...form, description: event.target.value })}
                                placeholder="Ringkasan produk (opsional)"
                            />
                        </Field>

                        <div className="flex flex-wrap items-center gap-5 pt-1">
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.is_favorite}
                                    onChange={(event) => setForm({ ...form, is_favorite: event.target.checked })}
                                    className="w-4 h-4 accent-accent"
                                />
                                Tampilkan di Favorit
                            </label>
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.is_active}
                                    onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                                    className="w-4 h-4 accent-accent"
                                />
                                Aktif
                            </label>
                        </div>
                    </div>
                </FormModal>
            )}
        </div>
    );
}
