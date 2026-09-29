import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, Check, Search, User, UserPlus, X } from 'lucide-react';
import { api } from '../../api/client';
import { notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field, FieldRow } from '../admin/FormModal';

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
};

/**
 * Pemilih pelanggan untuk layar kasir.
 *
 * Dua alur: cari pelanggan yang sudah ada, atau daftarkan pelanggan baru
 * (perorangan / badan usaha) tanpa meninggalkan halaman transaksi.
 */
export default function CashierCustomerPicker({ selected, onChange }) {
    const queryClient = useQueryClient();
    const [term, setTerm] = useState('');
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});

    const needle = term.trim();

    const { data: response, isFetching } = useQuery({
        queryKey: ['cashier-customers', needle],
        queryFn: async () => (await api.get('/customers', { params: { search: needle || undefined } })).data,
        enabled: needle.length > 0,
    });

    const results = response?.data ?? [];

    const createCustomer = useMutation({
        mutationFn: (payload) => api.post('/customers', payload),
        onSuccess: (result) => {
            queryClient.invalidateQueries({ queryKey: ['cashier-customers'] });
            queryClient.invalidateQueries({ queryKey: ['master-customers'] });
            onChange(result.data.data);
            setForm(null);
            setTerm('');
            notifySuccess('Pelanggan tersimpan dan langsung dipilih.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Pelanggan tidak dapat disimpan.');
        },
    });

    const openForm = (customerType = 'individual') =>
        setForm({ ...EMPTY_FORM, customer_type: customerType });

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
            Object.entries(form).filter(([, value]) => String(value ?? '').trim() !== ''),
        );

        createCustomer.mutate(payload);
    };

    const canSubmit = form
        ? form.customer_type === 'business'
            ? ['name', 'email', 'phone', ...BUSINESS_FIELDS].every((key) => String(form[key] ?? '').trim())
            : ['name', 'email', 'phone'].every((key) => String(form[key] ?? '').trim())
        : false;

    return (
        <div>
            <div className="flex items-center justify-between gap-2 mb-2">
                <span className="label !mb-0">Pelanggan</span>
                {selected && (
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        className="text-[11px] font-semibold text-negative hover:underline cursor-pointer"
                    >
                        Lepas
                    </button>
                )}
            </div>

            {selected ? (
                <div className="flex items-center gap-2 bg-accent-soft rounded-xl px-3 py-2.5">
                    {selected.customer_type === 'business' ? (
                        <Building2 size={16} className="text-accent-ink shrink-0" />
                    ) : (
                        <User size={16} className="text-accent-ink shrink-0" />
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="text-sm font-semibold text-accent-ink truncate">
                            {selected.company_name || selected.name}
                        </div>
                        <div className="text-[11px] text-accent-ink/80 truncate">
                            {selected.company_name ? `${selected.name} · ` : ''}
                            {selected.customer_type === 'business' ? 'Badan Usaha' : 'Perorangan'}
                        </div>
                    </div>
                    <Check size={16} className="text-accent-ink shrink-0" />
                </div>
            ) : (
                <>
                    <div className="relative">
                        <Search
                            size={15}
                            className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10"
                        />
                        <input
                            className="input !pl-9"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder="Cari nama, telepon, atau NIK..."
                        />
                    </div>

                    {needle && (
                        <div className="mt-2 max-h-56 overflow-y-auto scrollbar-thin border border-line rounded-xl bg-surface">
                            {isFetching ? (
                                <p className="text-xs text-muted text-center py-3">Mencari...</p>
                            ) : results.length === 0 ? (
                                <p className="text-xs text-muted text-center py-3">Pelanggan tidak ditemukan.</p>
                            ) : (
                                results.map((customer) => (
                                    <button
                                        key={customer.id}
                                        type="button"
                                        onClick={() => {
                                            onChange(customer);
                                            setTerm('');
                                        }}
                                        className="w-full text-left px-3 py-2 hover:bg-surface-2 transition-colors cursor-pointer border-b border-line last:border-b-0"
                                    >
                                        <div className="text-sm font-semibold truncate flex items-center gap-1.5">
                                            {customer.customer_type === 'business' ? (
                                                <Building2 size={12} className="text-faint shrink-0" />
                                            ) : (
                                                <User size={12} className="text-faint shrink-0" />
                                            )}
                                            {customer.company_name || customer.name}
                                        </div>
                                        <div className="text-[11px] text-muted truncate">
                                            {customer.company_name ? `${customer.name} · ` : ''}
                                            {customer.phone}
                                        </div>
                                    </button>
                                ))
                            )}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-2 mt-2">
                        {TYPES.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => openForm(item.key)}
                                className="btn btn-ghost !text-[11px] flex items-center justify-center gap-1.5"
                            >
                                <item.icon size={13} /> {item.label}
                            </button>
                        ))}
                    </div>
                </>
            )}

            {form && (
                <FormModal
                    title={form.customer_type === 'business' ? 'Pelanggan Badan Usaha' : 'Pelanggan Perorangan'}
                    subtitle="Data tersimpan ke master pelanggan."
                    width="max-w-2xl"
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    pending={createCustomer.isPending}
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
                                            form.customer_type === item.key
                                                ? 'bg-accent-soft text-accent-ink'
                                                : 'bg-surface-3 text-muted hover:bg-surface-3'
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
                                            onChange={(event) =>
                                                setForm({ ...form, nik: event.target.value.replace(/\D/g, '') })
                                            }
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

                        <Field
                            label={form.customer_type === 'business' ? 'Nama Contacts' : 'Nama Lengkap'}
                            error={errors.name}
                        >
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
                                className="input min-h-[60px] resize-y"
                                value={form.address}
                                onChange={(event) => setForm({ ...form, address: event.target.value })}
                                placeholder="Alamat lengkap (opsional)"
                            />
                        </Field>
                    </div>
                </FormModal>
            )}
        </div>
    );
}
