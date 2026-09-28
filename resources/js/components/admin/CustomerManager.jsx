import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Building2, ChevronLeft, ChevronRight, Pencil, Plus, Search, Trash2, User,
} from 'lucide-react';
import { api } from '../../api/client';
import { confirmAction, notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field, FieldRow } from './FormModal';

const TYPES = [
    { key: 'individual', label: 'Perorangan', icon: User },
    { key: 'business', label: 'Badan Usaha', icon: Building2 },
];

const BUSINESS_FIELDS = ['company_name', 'nik', 'npwp', 'province', 'city', 'postal_code', 'country'];

const EMPTY_FORM = {
    customer_type: 'individual',
    name: '',
    email: '',
    phone: '',
    address: '',
    company_name: '',
    nik: '',
    npwp: '',
    province: '',
    city: '',
    postal_code: '',
    country: 'Indonesia',
    notes: '',
    is_active: true,
};

const toForm = (customer) =>
    customer
        ? {
              id: customer.id,
              ...EMPTY_FORM,
              ...Object.fromEntries(
                  ['customer_type', 'name', 'email', 'phone', 'address', 'company_name', 'nik', 'npwp', 'province', 'city', 'postal_code', 'country', 'notes'].map(
                      (key) => [key, customer[key] ?? (key === 'customer_type' ? 'individual' : '')],
                  ),
              ),
              is_active: Boolean(customer.is_active),
          }
        : EMPTY_FORM;

export default function CustomerManager() {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [type, setTypeFilter] = useState('');
    const [page, setPage] = useState(1);
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});

    const { data: response, isLoading } = useQuery({
        queryKey: ['master-customers', search, type, page],
        queryFn: async () =>
            (await api.get('/admin/customers', { params: { search: search || undefined, customer_type: type || undefined, page } })).data,
    });

    const customers = response?.data ?? [];
    const meta = response?.meta ?? { total: 0, current_page: 1, last_page: 1 };

    const saveCustomer = useMutation({
        mutationFn: (payload) =>
            payload.id ? api.put(`/admin/customers/${payload.id}`, payload) : api.post('/admin/customers', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['master-customers'] });
            setForm(null);
            notifySuccess('Pelanggan tersimpan.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Pelanggan tidak dapat disimpan.');
        },
    });

    const removeCustomer = async (customer) => {
        const confirmed = await confirmAction(
            `Hapus pelanggan "${customer.display_name}"?`,
            'Pelanggan yang sudah punya transaksi tidak bisa dihapus, nonaktifkan saja.',
            'Ya, hapus',
        );

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/admin/customers/${customer.id}`);
            queryClient.invalidateQueries({ queryKey: ['master-customers'] });
            notifySuccess('Pelanggan dihapus.');
        } catch (error) {
            notifyError('Gagal', error.response?.data?.message ?? 'Pelanggan tidak dapat dihapus.');
        }
    };

    const openForm = (customer = null) => {
        setErrors({});
        setForm(toForm(customer));
    };

    const setFormType = (value) =>
        setForm((current) => ({
            ...current,
            customer_type: value,
            ...(value === 'individual'
                ? Object.fromEntries(BUSINESS_FIELDS.map((key) => [key, '']))
                : {}),
        }));

    const submitForm = () => {
        const payload = Object.fromEntries(
            Object.entries(form).filter(([key]) => key !== 'id' && String(form[key] ?? '').trim() !== ''),
        );

        saveCustomer.mutate({ ...payload, is_active: form.is_active });
    };

    const canSubmit = form
        ? form.customer_type === 'business'
            ? ['name', 'email', 'phone', ...BUSINESS_FIELDS].every((key) => String(form[key] ?? '').trim())
            : ['name', 'email', 'phone'].every((key) => String(form[key] ?? '').trim())
        : false;

    return (
        <div className="space-y-4">
            <div className="card">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 className="font-bold flex items-center gap-2"><User size={17} /> Master Pelanggan</h3>
                        <p className="text-sm text-muted mt-0.5">Data perorangan dan badan usaha untuk pencatatan transaksi.</p>
                    </div>
                    <button className="btn btn-primary" onClick={() => openForm()}>
                        <Plus size={16} /> Tambah Pelanggan
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
                            placeholder="Cari nama, email, atau telepon..."
                        />
                    </div>
                    <select
                        className="select !w-auto min-w-[160px]"
                        value={type}
                        onChange={(event) => {
                            setTypeFilter(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">Semua tipe</option>
                        {TYPES.map((item) => (
                            <option key={item.key} value={item.key}>{item.label}</option>
                        ))}
                    </select>
                </div>

                {isLoading ? (
                    <p className="text-muted text-sm text-center py-8">Memuat pelanggan...</p>
                ) : customers.length === 0 ? (
                    <p className="text-muted text-sm text-center py-8">Belum ada pelanggan yang cocok.</p>
                ) : (
                    <div className="border border-line rounded-xl overflow-hidden bg-surface">
                        <table className="w-full">
                            <thead className="border-b border-line">
                                <tr>
                                    <th className="table-head">Pelanggan</th>
                                    <th className="table-head">Tipe</th>
                                    <th className="table-head">Wilayah</th>
                                    <th className="table-head text-right">Transaksi</th>
                                    <th className="table-head text-center">Status</th>
                                    <th className="table-head text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {customers.map((customer) => (
                                    <tr key={customer.id}>
                                        <td className="table-cell">
                                            <div className="font-semibold text-sm truncate">{customer.display_name}</div>
                                            <div className="text-[11px] text-muted truncate">
                                                {customer.email} {customer.phone ? `· ${customer.phone}` : ''}
                                            </div>
                                        </td>
                                        <td className="table-cell">
                                            <span className={`badge ${customer.customer_type === 'business' ? 'badge-accent' : 'badge-pending'}`}>
                                                {customer.customer_type === 'business' ? 'Badan Usaha' : 'Perorangan'}
                                            </span>
                                        </td>
                                        <td className="table-cell text-sm text-muted">{customer.region || '-'}</td>
                                        <td className="table-cell text-right text-sm">{customer.orders_count ?? 0}</td>
                                        <td className="table-cell text-center">
                                            {customer.is_active ? (
                                                <span className="badge badge-done">Aktif</span>
                                            ) : (
                                                <span className="badge badge-pending">Nonaktif</span>
                                            )}
                                        </td>
                                        <td className="table-cell">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    className="btn-icon w-8 h-8 text-muted hover:bg-surface-3"
                                                    onClick={() => openForm(customer)}
                                                    title="Edit"
                                                >
                                                    <Pencil size={15} />
                                                </button>
                                                <button
                                                    className="btn-icon w-8 h-8 text-negative hover:bg-surface-3"
                                                    onClick={() => removeCustomer(customer)}
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
                    <span className="text-xs text-muted">{meta.total} pelanggan</span>
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
                    title={form.id ? 'Edit Pelanggan' : 'Tambah Pelanggan'}
                    width="max-w-2xl"
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    pending={saveCustomer.isPending}
                    disabled={!canSubmit}
                >
                    <div className="space-y-3">
                        <div>
                            <label className="label">Tipe Pelanggan</label>
                            <div className="grid grid-cols-2 gap-2">
                                {TYPES.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        onClick={() => setFormType(item.key)}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer ${
                                            form.customer_type === item.key ? 'bg-accent-soft text-accent-ink' : 'bg-surface-3 text-muted hover:bg-surface-3'
                                        }`}
                                    >
                                        <item.icon size={14} /> {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {form.customer_type === 'business' && (
                            <>
                                <Field label="Nama Perusahaan" error={errors.company_name}>
                                    <input
                                        className="input"
                                        value={form.company_name}
                                        onChange={(event) => setForm({ ...form, company_name: event.target.value })}
                                        placeholder="mis. PT Nusantara Jaya"
                                    />
                                </Field>
                                <FieldRow>
                                    <Field label="NIK Penanggung Jawab" error={errors.nik} hint="16 digit angka.">
                                        <input
                                            className="input"
                                            inputMode="numeric"
                                            maxLength={16}
                                            value={form.nik}
                                            onChange={(event) => setForm({ ...form, nik: event.target.value.replace(/\D/g, '') })}
                                            placeholder="3273010101900001"
                                        />
                                    </Field>
                                    <Field label="NPWP" error={errors.npwp}>
                                        <input
                                            className="input"
                                            value={form.npwp}
                                            onChange={(event) => setForm({ ...form, npwp: event.target.value })}
                                            placeholder="01.234.567.8-901.000"
                                        />
                                    </Field>
                                </FieldRow>
                                <FieldRow>
                                    <Field label="Kota / Kabupaten" error={errors.city}>
                                        <input
                                            className="input"
                                            value={form.city}
                                            onChange={(event) => setForm({ ...form, city: event.target.value })}
                                            placeholder="mis. Bandung"
                                        />
                                    </Field>
                                    <Field label="Provinsi" error={errors.province}>
                                        <input
                                            className="input"
                                            value={form.province}
                                            onChange={(event) => setForm({ ...form, province: event.target.value })}
                                            placeholder="mis. Jawa Barat"
                                        />
                                    </Field>
                                </FieldRow>
                                <FieldRow>
                                    <Field label="Kode Pos" error={errors.postal_code}>
                                        <input
                                            className="input"
                                            value={form.postal_code}
                                            onChange={(event) => setForm({ ...form, postal_code: event.target.value })}
                                            placeholder="40115"
                                        />
                                    </Field>
                                    <Field label="Negara" error={errors.country}>
                                        <input
                                            className="input"
                                            value={form.country}
                                            onChange={(event) => setForm({ ...form, country: event.target.value })}
                                        />
                                    </Field>
                                </FieldRow>
                            </>
                        )}

                        <Field label={form.customer_type === 'business' ? 'Nama Contacts' : 'Nama Lengkap'} error={errors.name}>
                            <input
                                className="input"
                                value={form.name}
                                onChange={(event) => setForm({ ...form, name: event.target.value })}
                                placeholder="mis. Siti Aminah"
                                autoFocus
                            />
                        </Field>

                        <FieldRow>
                            <Field label="Email" error={errors.email}>
                                <input
                                    className="input"
                                    type="email"
                                    value={form.email}
                                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                                    placeholder="nama@email.com"
                                />
                            </Field>
                            <Field label="Nomor HP" error={errors.phone}>
                                <input
                                    className="input"
                                    inputMode="tel"
                                    value={form.phone}
                                    onChange={(event) => setForm({ ...form, phone: event.target.value })}
                                    placeholder="0812 3456 7890"
                                />
                            </Field>
                        </FieldRow>

                        <Field label="Alamat" error={errors.address}>
                            <textarea
                                className="input min-h-[70px] resize-y"
                                value={form.address}
                                onChange={(event) => setForm({ ...form, address: event.target.value })}
                                placeholder="Alamat lengkap (opsional)"
                            />
                        </Field>

                        <Field label="Catatan" error={errors.notes}>
                            <textarea
                                className="input min-h-[60px] resize-y"
                                value={form.notes}
                                onChange={(event) => setForm({ ...form, notes: event.target.value })}
                                placeholder="Catatan internal (opsional)"
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
