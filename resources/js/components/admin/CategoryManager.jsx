import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronDown, ChevronUp, GripVertical, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import useListDrag from '../../hooks/useListDrag';
import { api } from '../../api/client';
import { confirmAction, notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field } from './FormModal';

const EMPTY_FORM = { name: '', is_active: true };

export default function CategoryManager() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});

    const { data: categories = [] } = useQuery({
        queryKey: ['master-categories'],
        queryFn: async () => (await api.get('/admin/categories')).data.data,
    });

    const refresh = () => {
        queryClient.invalidateQueries({ queryKey: ['master-categories'] });
        queryClient.invalidateQueries({ queryKey: ['settings'] });
    };

    const saveCategory = useMutation({
        mutationFn: (payload) =>
            payload.id ? api.put(`/admin/categories/${payload.id}`, payload) : api.post('/admin/categories', payload),
        onSuccess: (response) => {
            refresh();
            setForm(null);
            notifySuccess('Kategori tersimpan.', response.data.data.name);
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Kategori tidak dapat disimpan.');
        },
    });

    const saveOrder = useMutation({
        mutationFn: (order) => api.put('/admin/categories/order', { order }),
        onSuccess: refresh,
        onError: (error) => {
            refresh();
            notifyError('Gagal', error.response?.data?.message ?? 'Urutan kategori tidak dapat disimpan.');
        },
    });

    const removeCategory = async (category) => {
        const confirmed = await confirmAction(
            `Hapus kategori "${category.name}"?`,
            'Kategori hanya bisa dihapus jika tidak dipakai produk.',
            'Ya, hapus',
        );

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/admin/categories/${category.id}`);
            refresh();
            notifySuccess('Kategori dihapus.');
        } catch (error) {
            notifyError('Gagal', error.response?.data?.message ?? 'Kategori tidak dapat dihapus.');
        }
    };

    const moveCategory = (from, to) => {
        const names = categories.map((item) => item.name);

        if (to < 0 || to >= names.length || from === to || from < 0) {
            return;
        }

        const [moved] = names.splice(from, 1);
        names.splice(to, 0, moved);
        saveOrder.mutate(names);
    };

    const { dragging, overKey, startDrag } = useListDrag((fromKey, toKey) =>
        moveCategory(
            categories.findIndex((item) => item.name === fromKey),
            categories.findIndex((item) => item.name === toKey),
        ),
    );

    const openForm = (category = null) => {
        setErrors({});
        setForm(category ? { id: category.id, name: category.name, is_active: category.is_active } : EMPTY_FORM);
    };

    return (
        <div className="space-y-4">
            <div className="card">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 className="font-bold flex items-center gap-2"><Tags size={17} /> Master Kategori</h3>
                        <p className="text-sm text-muted mt-0.5">
                            Kategori dipakai untuk mengelompokkan produk. Urutan di sini langsung berlaku pada katalog kasir.
                        </p>
                    </div>
                    <button className="btn btn-primary" onClick={() => openForm()}>
                        <Plus size={16} /> Tambah Kategori
                    </button>
                </div>

                {categories.length === 0 ? (
                    <p className="text-muted text-sm text-center py-8">Belum ada kategori. Tambahkan kategori pertama Anda.</p>
                ) : (
                    <ul className="space-y-2">
                        {categories.map((category, index) => (
                            <li
                                key={category.name}
                                data-drag-key={category.name}
                                className={`flex items-center gap-2 rounded-xl px-2 py-2.5 transition-colors ${
                                    dragging === category.name
                                        ? 'opacity-40'
                                        : overKey === category.name
                                          ? 'bg-accent-soft'
                                          : 'bg-surface-2'
                                }`}
                            >
                                <button
                                    type="button"
                                    onPointerDown={(event) => startDrag(event, category.name)}
                                    className="touch-none cursor-grab active:cursor-grabbing text-faint hover:text-muted shrink-0 transition-colors"
                                    title="Seret untuk mengurutkan"
                                >
                                    <GripVertical size={16} />
                                </button>
                                <span className="w-7 h-7 rounded-lg bg-surface-3 text-muted text-xs font-bold flex items-center justify-center shrink-0">
                                    {index + 1}
                                </span>
                                <div className="flex-1 min-w-0">
                                    <div className="text-sm font-semibold truncate flex items-center gap-2">
                                        {category.name}
                                        {!category.is_registered && <span className="badge badge-pending">Belum terdaftar</span>}
                                    </div>
                                    <div className="text-[11px] text-muted">{category.total} produk</div>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    <button
                                        className="btn-icon w-8 h-8 bg-surface-3 text-muted hover:text-content disabled:opacity-30 disabled:cursor-not-allowed"
                                        onClick={() => moveCategory(index, index - 1)}
                                        disabled={index === 0 || saveOrder.isPending}
                                        title="Naikkan"
                                    >
                                        <ChevronUp size={16} />
                                    </button>
                                    <button
                                        className="btn-icon w-8 h-8 bg-surface-3 text-muted hover:text-content disabled:opacity-30 disabled:cursor-not-allowed"
                                        onClick={() => moveCategory(index, index + 1)}
                                        disabled={index === categories.length - 1 || saveOrder.isPending}
                                        title="Turunkan"
                                    >
                                        <ChevronDown size={16} />
                                    </button>
                                    <button
                                        className="btn-icon w-8 h-8 text-muted hover:bg-surface-3"
                                        onClick={() => openForm(category)}
                                        title="Edit"
                                    >
                                        <Pencil size={15} />
                                    </button>
                                    {category.is_registered ? (
                                        <button
                                            className="btn-icon w-8 h-8 text-negative hover:bg-surface-3"
                                            onClick={() => removeCategory(category)}
                                            title="Hapus"
                                        >
                                            <Trash2 size={15} />
                                        </button>
                                    ) : (
                                        <button
                                            className="btn btn-secondary !px-2.5 !py-1.5"
                                            onClick={() => saveCategory.mutate({ name: category.name, is_active: true })}
                                            disabled={saveCategory.isPending}
                                            title="Daftarkan kategori ini"
                                        >
                                            <Plus size={14} /> Daftarkan
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {form && (
                <FormModal
                    title={form.id ? 'Edit Kategori' : 'Tambah Kategori'}
                    subtitle="Mengganti nama kategori ikut memperbarui produk yang memakainya."
                    onClose={() => setForm(null)}
                    onSubmit={() => saveCategory.mutate({ ...form, name: form.name.trim() })}
                    pending={saveCategory.isPending}
                    disabled={!form.name.trim()}
                >
                    <div className="space-y-3">
                        <Field label="Nama Kategori" error={errors.name}>
                            <input
                                className="input"
                                value={form.name}
                                onChange={(event) => setForm({ ...form, name: event.target.value })}
                                placeholder="mis. Makanan / Minuman"
                                autoFocus
                            />
                        </Field>
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
                </FormModal>
            )}
        </div>
    );
}
