import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, Check, Search, User, UserPlus, Users, X } from 'lucide-react';
import { api } from '../../api/client';
import { useClickOutside } from '../../hooks/useClickOutside';
import { notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field, FieldRow } from '../admin/FormModal';

const WALK_IN_LABEL = 'Pelanggan Umum';

/**
 * Bentuk form pelanggan baru.
 *
 * Semua field ikut dikirim supaya kasir bisa mengisi yang memang diketahuinya
 * di tempat — tidak perlu pindah ke menu admin dulu. Yang wajib hanya nama:
 * field lain boleh kosong dan dilengkapi belakangan.
 */
const EMPTY_FORM = {
    customer_type: 'individual',
    name: '',
    phone: '',
    email: '',
    address: '',
    company_name: '',
    nik: '',
    npwp: '',
    province: '',
    city: '',
    postal_code: '',
    country: 'Indonesia',
};

const CUSTOMER_TYPES = [
    { key: 'individual', label: 'Perorangan', icon: User },
    { key: 'business', label: 'Perusahaan', icon: Building2 },
];

function displayName(customer) {
    return customer.company_name || customer.name;
}

function displayDetail(customer) {
    if (customer.customer_type === 'business') {
        return [customer.name, 'Badan Usaha'].filter(Boolean).join(' · ');
    }
    return customer.phone || '';
}

function TypeIcon({ customer }) {
    return customer.customer_type === 'business' ? (
        <Building2 size={12} className="text-faint shrink-0" />
    ) : (
        <User size={12} className="text-faint shrink-0" />
    );
}

/**
 * Pemilih pelanggan untuk layar kasir.
 *
 * Satu dropdown pencarian: pilih pelanggan yang sudah ada, atau ketika tidak
 * ketemu, buat pelanggan baru lewat entri "Daftarkan baru" di bagian bawah
 * daftar. Kasir hanya mendaftarkan pelanggan perorangan; data badan usaha
 * (NPWP, NIK penanggung jawab, dan sejenisnya) lengkap di menu admin.
 */
export default function CashierCustomerSelect({ selected, onChange }) {
    const queryClient = useQueryClient();
    const [term, setTerm] = useState('');
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});
    const [needle, setNeedle] = useState('');
    // Indeks opsi yang sedang disorot saat memakai keyboard. -1 berarti belum
    // ada yang dipilih, jadi Enter pertama kali menyorot opsi teratas.
    const [activeIndex, setActiveIndex] = useState(-1);
    const listRef = useRef(null);
    const ref = useClickOutside(() => setOpen(false));

    // Debounce agar tiap ketikan tidak langsung menembak API.
    useEffect(() => {
        const timer = setTimeout(() => setNeedle(term.trim()), 250);
        return () => clearTimeout(timer);
    }, [term]);

    const { data: response, isFetching } = useQuery({
        queryKey: ['cashier-customers', needle],
        queryFn: async () =>
            (await api.get('/customers', { params: { search: needle || undefined, per_page: 20 } })).data,
    });

    const results = response?.data ?? [];

    const exactMatch = results.some(
        (customer) => displayName(customer).toLowerCase() === needle.toLowerCase(),
    );

    /** Opsi "Daftarkan sebagai pelanggan baru" hanya muncul kalau ada teks
     *  yang diketik dan tidak sama persis dengan pelanggan yang ada. */
    const canCreate = Boolean(needle) && !exactMatch;

    // Urutan ini harus sama dengan urutan tombol di dropdown.
    const options = [
        { kind: 'walkin' },
        ...results.map((customer) => ({ kind: 'customer', customer })),
        ...(canCreate ? [{ kind: 'create' }] : []),
    ];

    const applyOption = (option) => {
        if (!option) return;
        if (option.kind === 'walkin') onChange(null);
        else if (option.kind === 'customer') onChange(option.customer);
        else openForm(needle);
        setTerm('');
        setNeedle('');
        setOpen(false);
        setActiveIndex(-1);
    };

    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            if (open) {
                event.preventDefault();
                setOpen(false);
                setActiveIndex(-1);
            }
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) {
                setOpen(true);
                setActiveIndex(0);
                return;
            }
            if (options.length === 0) return;
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex((current) => {
                const next = current < 0
                    ? (step === 1 ? 0 : options.length - 1)
                    : (current + step + options.length) % options.length;
                return next;
            });
            return;
        }

        if (event.key === 'Enter') {
            // Enter saat mengetik nama baru: langsung buka form pendaftaran
            // supaya kasir tidak perlu melepas fokus ke dropdown dulu.
            if (!open && canCreate) {
                event.preventDefault();
                openForm(needle);
                setOpen(false);
                setActiveIndex(-1);
                return;
            }
            if (!open) return;
            event.preventDefault();
            applyOption(options[activeIndex] ?? options[0]);
        }
    };

    // Sorotan keyboard ikut ter-scroll supaya opsi aktif tidak keluar layar.
    useEffect(() => {
        if (activeIndex < 0) return;
        listRef.current
            ?.querySelector(`[data-option-index="${activeIndex}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    const createCustomer = useMutation({
        mutationFn: (payload) => api.post('/customers', payload),
        onSuccess: (result) => {
            queryClient.invalidateQueries({ queryKey: ['cashier-customers'] });
            queryClient.invalidateQueries({ queryKey: ['master-customers'] });
            onChange(result.data.data);
            setForm(null);
            setTerm('');
            setOpen(false);
            notifySuccess('Pelanggan tersimpan dan langsung dipilih.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Pelanggan tidak dapat disimpan.');
        },
    });

    const openForm = (prefillName = '') =>
        setForm({ ...EMPTY_FORM, name: prefillName });

    /**
     * Kirim hanya field yang benar-benar diisi, supaya kolom opsional tidak
     * tertimpa string kosong yang berbeda maknanya dari "tidak diisi".
     */
    const submitForm = () => {
        const payload = {
            customer_type: form.customer_type,
            ...Object.fromEntries(
                Object.entries(form)
                    .filter(([key, value]) => key !== 'customer_type' && String(value ?? '').trim() !== '')
                    .map(([key, value]) => [key, value.trim()]),
            ),
        };

        createCustomer.mutate(payload);
    };

    const setFormType = (value) =>
        setForm((current) => ({ ...current, customer_type: value }));

    const canSubmit = form ? form.name.trim() !== '' : false;

    return (
        <div>
            <div className="flex items-center gap-1">
                <span className="label !mb-0 w-20 shrink-0">Pelanggan</span>
                <div className="flex-1 min-w-0">
                    <div className="relative" ref={ref}>
                        <Search
                            size={15}
                            className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10"
                        />
                        <input
                            className="input !pl-9 !pr-8 !py-1.5"
                            value={open ? term : selected ? displayName(selected) : WALK_IN_LABEL}
                            onFocus={() => {
                                setOpen(true);
                                setActiveIndex(-1);
                                if (selected) setTerm('');
                            }}
                            onChange={(event) => {
                                setTerm(event.target.value);
                                setOpen(true);
                                setActiveIndex(-1);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder="Cari nama..."
                        />
                        {(selected || needle) && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (selected) onChange(null);
                                    setTerm('');
                                    setOpen(true);
                                }}
                                title="Hapus pilihan"
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-faint hover:text-content cursor-pointer"
                            >
                                <X size={14} />
                            </button>
                        )}

                        {open && (
                            <div ref={listRef} className="absolute left-0 right-0 top-10 max-h-72 overflow-y-auto scrollbar-thin bg-surface rounded-xl shadow-xl z-40 py-1">
                                <button
                                    type="button"
                                    data-option-index="0"
                                    onMouseEnter={() => setActiveIndex(0)}
                                    onClick={() => applyOption({ kind: 'walkin' })}
                                    className={`w-full flex items-center gap-2 px-2 py-1.5 text-sm text-left cursor-pointer transition-colors ${
                                        activeIndex === 0 || !selected
                                        ? 'bg-accent-soft text-accent-ink font-semibold'
                                        : 'hover:bg-surface-2'
                                    }`}
                                >
                                    <Users size={14} className="shrink-0" />
                                    {WALK_IN_LABEL}
                                    {!selected && <Check size={14} className="ml-auto" />}
                                </button>

                                {isFetching ? (
                                    <p className="text-xs text-muted text-center py-2">Mencari...</p>
                                ) : (
                                    results.map((customer, index) => (
                                        <button
                                            key={customer.id}
                                            type="button"
                                            data-option-index={index + 1}
                                            onMouseEnter={() => setActiveIndex(index + 1)}
                                            onClick={() => applyOption({ kind: 'customer', customer })}
                                            className={`w-full flex items-center gap-2 px-2 py-1.5 text-left cursor-pointer transition-colors ${
                                                activeIndex === index + 1 || selected?.id === customer.id
                                                    ? 'bg-accent-soft text-accent-ink font-semibold'
                                                    : 'hover:bg-surface-2'
                                            }`}
                                        >
                                            <TypeIcon customer={customer} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-semibold truncate">
                                                    {displayName(customer)}
                                                </span>
                                                <span className="block text-[11px] opacity-70 truncate">
                                                    {displayDetail(customer)}
                                                </span>
                                            </span>
                                            {selected?.id === customer.id && <Check size={14} className="shrink-0" />}
                                        </button>
                                    ))
                                )}

                                {canCreate && (
                                    <>
                                        <div className="border-t border-line my-px" />
                                        <button
                                            type="button"
                                            data-option-index={options.length - 1}
                                            onMouseEnter={() => setActiveIndex(options.length - 1)}
                                            onClick={() => applyOption({ kind: 'create' })}
                                            className={`w-full flex items-center gap-2 px-2 py-1.5 text-sm text-left font-semibold transition-colors cursor-pointer ${activeIndex === options.length - 1 ? 'bg-accent-soft text-accent-ink' : 'text-accent-ink hover:bg-accent-soft'}`}
                                        >
                                            <UserPlus size={14} className="shrink-0" />
                                            <span className="truncate">Daftarkan &ldquo;{needle}&rdquo; sebagai pelanggan baru</span>
                                        </button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                </div>

            </div>

            {form && (
                <FormModal
                    title="Pelanggan Baru"
                    width="max-w-2xl"
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    pending={createCustomer.isPending}
                    disabled={!canSubmit}
                >
                    <div className="space-y-1">
                        <div>
                            <span className="label">Tipe Pelanggan</span>
                            <div className="grid grid-cols-2 gap-1">
                                {CUSTOMER_TYPES.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        onClick={() => setFormType(item.key)}
                                        className={`px-3 py-1.5 rounded-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer ${
                                            form.customer_type === item.key
                                                ? 'bg-accent-soft text-accent-ink'
                                                : 'bg-surface-2 text-muted hover:bg-surface-3'
                                        }`}
                                    >
                                        <item.icon size={14} /> {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <Field label="Nama" error={errors.name}>
                            <input
                                className="input"
                                value={form.name}
                                onChange={(event) => setForm({ ...form, name: event.target.value })}
                                placeholder="mis. Siti Aminah"
                                autoFocus
                            />
                        </Field>

                        <FieldRow>
                            <Field label="Nomor HP" error={errors.phone} hint="Opsional.">
                                <input
                                    className="input"
                                    inputMode="tel"
                                    value={form.phone}
                                    onChange={(event) => setForm({ ...form, phone: event.target.value })}
                                    placeholder="0812 3456 7890"
                                />
                            </Field>
                            <Field label="Email" error={errors.email} hint="Opsional.">
                                <input
                                    className="input"
                                    type="email"
                                    value={form.email}
                                    onChange={(event) => setForm({ ...form, email: event.target.value })}
                                    placeholder="nama@email.com"
                                />
                            </Field>
                        </FieldRow>

                        {form.customer_type === 'business' && (
                            <>
                                <Field label="Nama Perusahaan" error={errors.company_name} hint="Opsional.">
                                    <input
                                        className="input"
                                        value={form.company_name}
                                        onChange={(event) =>
                                            setForm({ ...form, company_name: event.target.value })
                                        }
                                        placeholder="mis. PT Nusantara Jaya"
                                    />
                                </Field>

                                <FieldRow>
                                    <Field label="NIK Penanggung Jawab" error={errors.nik} hint="16 digit, opsional.">
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
                                    <Field label="NPWP" error={errors.npwp} hint="Opsional.">
                                        <input
                                            className="input"
                                            value={form.npwp}
                                            onChange={(event) => setForm({ ...form, npwp: event.target.value })}
                                            placeholder="01.234.567.8-901.000"
                                        />
                                    </Field>
                                </FieldRow>

                                <FieldRow>
                                    <Field label="Kota / Kabupaten" error={errors.city} hint="Opsional.">
                                        <input
                                            className="input"
                                            value={form.city}
                                            onChange={(event) => setForm({ ...form, city: event.target.value })}
                                            placeholder="mis. Bandung"
                                        />
                                    </Field>
                                    <Field label="Provinsi" error={errors.province} hint="Opsional.">
                                        <input
                                            className="input"
                                            value={form.province}
                                            onChange={(event) => setForm({ ...form, province: event.target.value })}
                                            placeholder="mis. Jawa Barat"
                                        />
                                    </Field>
                                </FieldRow>

                                <FieldRow>
                                    <Field label="Kode Pos" error={errors.postal_code} hint="Opsional.">
                                        <input
                                            className="input"
                                            value={form.postal_code}
                                            onChange={(event) =>
                                                setForm({ ...form, postal_code: event.target.value })
                                            }
                                            placeholder="40115"
                                        />
                                    </Field>
                                    <Field label="Negara" error={errors.country} hint="Opsional.">
                                        <input
                                            className="input"
                                            value={form.country}
                                            onChange={(event) => setForm({ ...form, country: event.target.value })}
                                        />
                                    </Field>
                                </FieldRow>
                            </>
                        )}

                        <Field label="Alamat" error={errors.address} hint="Opsional.">
                            <textarea
                                className="input min-h-[50px] resize-y"
                                value={form.address}
                                onChange={(event) => setForm({ ...form, address: event.target.value })}
                                placeholder="Alamat lengkap"
                            />
                        </Field>
                    </div>
                </FormModal>
            )}
        </div>
    );
}
