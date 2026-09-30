import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Wallet, Pencil, Trash2, Plus, X, Landmark, Banknote, Settings2, Star, Package, Hash, Percent, CreditCard, QrCode,
    Smartphone, History, Palette, Check, Tags, User, Users, ShieldCheck,
} from 'lucide-react';
import CategoryManager from '../components/admin/CategoryManager';
import ProductManager from '../components/admin/ProductManager';
import CustomerManager from '../components/admin/CustomerManager';
import RoleManager from '../components/admin/RoleManager';
import AdminNavDropdown from '../components/admin/AdminNavDropdown';
import UserManager from '../components/admin/UserManager';
import Layout from '../components/Layout';
import { api, formatIDR } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { notifySuccess, notifyError, confirmAction } from '../utils/alerts';
import { THEME_ACCENTS, THEME_MODES, setAppearance, useAppearance } from '../theme';

const METHOD_TYPE_META = {
    kas: { label: 'Kas', color: 'bg-emerald-600', soft: 'bg-emerald-500/10 text-positive', icon: Banknote },
    bank: { label: 'Bank', color: 'bg-blue-600', soft: 'bg-blue-500/10 text-blue-700 dark:text-blue-300', icon: Landmark },
    qris: { label: 'QRIS', color: 'bg-purple-600', soft: 'bg-purple-500/10 text-purple-700 dark:text-purple-300', icon: QrCode },
    ewallet: { label: 'E-Wallet', color: 'bg-pink-600', soft: 'bg-pink-500/10 text-pink-700 dark:text-pink-300', icon: Smartphone },
};

const FLAG_META = [
    { key: 'cashier_show_favorites', label: 'Produk Favorit', icon: Star, desc: 'Tampilkan atau sembunyikan bagian produk favorit di katalog kasir.' },
    { key: 'cashier_show_stock', label: 'Stok Produk', icon: Package, desc: 'Tampilkan atau sembunyikan jumlah stok pada kartu produk.' },
    { key: 'cashier_enable_table', label: 'Input Nomor Meja', icon: Hash, desc: 'Aktifkan kewajiban memilih nomor meja saat transaksi.' },
    { key: 'cashier_enable_customer', label: 'Data Pelanggan', icon: User, desc: 'Tampilkan pemilih pelanggan (perorangan / badan usaha) di panel kasir.' },
    { key: 'cashier_enable_ppn', label: 'Penerapan PPN', icon: Percent, desc: 'Terapkan pajak PPN pada setiap transaksi kasir.' },
    { key: 'cashier_enable_prepay', label: 'Sistem Bayar Di Muka', icon: Wallet, desc: 'Izinkan status pembayaran di muka (seluruh uang diterima sebelum transaksi disimpan).' },
];

const EMPTY_FORM = { name: '', type: 'kas', payment_method_id: '', is_default: false, account_number: '', bank_name: '', is_active: true };
const EMPTY_METHOD = { code: '', type: 'kas', name: '', is_active: true };

export default function AdminDashboard() {
    const queryClient = useQueryClient();
    const { can } = useAuth();
    const [tab, setTab] = useState('settings');
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [methodModal, setMethodModal] = useState(null);
    const [methodForm, setMethodForm] = useState(EMPTY_METHOD);
    const [mutations, setMutations] = useState(null);
    const appearance = useAppearance();

    // Query di bawah memakai endpoint management yang butuh token + permission,
    // jadi hanya dibuat bila user memang berhak agar tidak memicu 403 sia-sia.
    const { data: adminSettings } = useQuery({
        queryKey: ['admin-settings'],
        queryFn: async () => (await api.get('/admin/settings')).data,
        enabled: can('settings.view'),
    });

    const flags = adminSettings?.data ?? {};

    const { data: accounts = [] } = useQuery({
        queryKey: ['cash-bank-accounts'],
        queryFn: async () => (await api.get('/cash-bank-accounts')).data.data,
        enabled: can('cash.view'),
    });

    const { data: products = [] } = useQuery({
        queryKey: ['admin-products'],
        queryFn: async () => (await api.get('/products')).data.data,
        enabled: can('product.view'),
    });

    const { data: methods = [] } = useQuery({
        queryKey: ['payment-methods'],
        queryFn: async () => (await api.get('/payment-methods')).data.data,
        enabled: can('payment_method.view'),
    });

    const { data: categories = [] } = useQuery({
        queryKey: ['master-categories'],
        queryFn: async () => (await api.get('/admin/categories')).data.data,
        enabled: can('category.view'),
    });

    const saveFlags = useMutation({
        mutationFn: (toSave) => api.put('/admin/settings', { flags: toSave }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
        },
        onError: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
            notifyError('Gagal', 'Pengaturan tidak dapat disimpan.');
        },
    });

    const saveAppearance = useMutation({
        mutationFn: (toSave) => api.put('/admin/settings', { appearance: toSave }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
            notifySuccess('Tampilan aplikasi diperbarui.');
        },
        onError: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-settings'] });
            notifyError('Gagal', 'Pengaturan tampilan tidak dapat disimpan.');
        },
    });

    const applyAppearance = (patch) => {
        const next = { ...appearance, ...patch };
        setAppearance(next);
        saveAppearance.mutate({ mode: next.mode, accent: next.accent });
    };

    const saveAccount = useMutation({
        mutationFn: async () => {
            const payload = {
                name: form.name,
                type: form.type,
                payment_method_id: form.payment_method_id ? Number(form.payment_method_id) : null,
                is_default: form.is_default,
                account_number: form.account_number,
                bank_name: form.type === 'bank' ? form.bank_name : null,
                is_active: form.is_active,
            };
            if (modal === 'edit') {
                return api.put(`/cash-bank-accounts/${form.id}`, payload);
            }
            return api.post('/cash-bank-accounts', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cash-bank-accounts'] });
            setModal(null);
            setForm(EMPTY_FORM);
            notifySuccess(modal === 'edit' ? 'Akun diperbarui.' : 'Akun ditambahkan.');
        },
        onError: (err) => notifyError('Gagal', err.response?.data?.message ?? 'Data akun tidak valid.'),
    });

    const makeDefaultAccount = useMutation({
        mutationFn: (account) =>
            api.put(`/cash-bank-accounts/${account.id}`, {
                name: account.name,
                type: account.type,
                payment_method_id: account.payment_method_id,
                is_default: true,
                account_number: account.account_number,
                bank_name: account.bank_name,
                is_active: account.is_active,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['cash-bank-accounts'] });
            queryClient.invalidateQueries({ queryKey: ['payment-methods'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
            notifySuccess('Rekening default diperbarui.');
        },
        onError: (err) => notifyError('Gagal', err.response?.data?.message ?? 'Rekening default tidak dapat disimpan.'),
    });

    const removeAccount = async (account) => {
        const ok = await confirmAction('Hapus akun?', `"${account.name}" akan dihapus permanen.`, 'Ya, hapus');
        if (!ok.isConfirmed) return;
        try {
            await api.delete(`/cash-bank-accounts/${account.id}`);
            queryClient.invalidateQueries({ queryKey: ['cash-bank-accounts'] });
            notifySuccess('Akun dihapus.');
        } catch {
            notifyError('Gagal', 'Akun tidak dapat dihapus.');
        }
    };

    const saveMethod = useMutation({
        mutationFn: async () => {
            const payload = {
                code: methodForm.code.trim().toLowerCase(),
                type: methodForm.type,
                name: methodForm.name.trim(),
                mdr_rate: 0,
                is_active: methodForm.is_active,
            };
            if (methodModal === 'edit') {
                return api.put(`/payment-methods/${methodForm.id}`, payload);
            }
            return api.post('/payment-methods', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['payment-methods'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
            setMethodModal(null);
            setMethodForm(EMPTY_METHOD);
            notifySuccess(methodModal === 'edit' ? 'Metode diperbarui.' : 'Metode ditambahkan.');
        },
        onError: (err) => notifyError('Gagal', err.response?.data?.message ?? 'Data metode tidak valid.'),
    });

    const removeMethod = async (method) => {
        const ok = await confirmAction('Hapus metode?', `"${method.name}" akan dihapus permanen.`, 'Ya, hapus');
        if (!ok.isConfirmed) return;
        try {
            await api.delete(`/payment-methods/${method.id}`);
            queryClient.invalidateQueries({ queryKey: ['payment-methods'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
            notifySuccess('Metode dihapus.');
        } catch {
            notifyError('Gagal', 'Metode tidak dapat dihapus.');
        }
    };

    const openMethodModal = (method = null) => {
        setMethodForm(
            method
                ? {
                      id: method.id,
                      code: method.code,
                      type: method.type ?? 'kas',
                      name: method.name,
                      is_active: method.is_active,
                  }
                : EMPTY_METHOD,
        );
        setMethodModal(method ? 'edit' : 'add');
    };

    const toggleFlag = (key, value) => {
        const next = { ...flags, [key]: value };
        queryClient.setQueryData(['admin-settings'], (old) => ({ ...old, data: next }));
        saveFlags.mutate(next);
    };

    const openModal = (account = null) => {
        setForm(
            account
                ? {
                      id: account.id,
                      name: account.name,
                      type: account.type,
                      payment_method_id: account.payment_method_id ? String(account.payment_method_id) : '',
                      is_default: account.is_default,
                      account_number: account.account_number ?? '',
                      bank_name: account.bank_name ?? '',
                      is_active: account.is_active,
                  }
                : EMPTY_FORM,
        );
        setModal(account ? 'edit' : 'add');
    };

    const openMutations = async (account) => {
        try {
            const { data } = await api.get(`/cash-bank-accounts/${account.id}/mutations`);
            setMutations({ account, receipts: data.data });
        } catch {
            notifyError('Gagal', 'Riwayat mutasi tidak dapat dimuat.');
        }
    };

    // Setiap menu punya permission; menu tanpa akses disembunyikan supaya user
    // tidak melihat halaman yang pasti ditolak backend.
    const navItems = [
        { key: 'settings', label: 'Kasir', icon: Settings2, permission: 'settings.view' },
        { key: 'appearance', label: 'Tampilan', icon: Palette, permission: 'settings.view' },
        { key: 'categories', label: 'Kategori', icon: Tags, count: categories.length, permission: 'category.view' },
        { key: 'products', label: 'Produk', icon: Package, count: products.length, permission: 'product.view' },
        { key: 'customers', label: 'Pelanggan', icon: User, permission: 'customer.view' },
        { key: 'methods', label: 'Metode', icon: CreditCard, count: methods.length, permission: 'payment_method.view' },
        { key: 'accounts', label: 'Kas & Bank', icon: Wallet, count: accounts.length, permission: 'cash.view' },
        { key: 'roles', label: 'Hak Akses', icon: ShieldCheck, permission: 'role.view' },
        { key: 'users', label: 'User', icon: Users, permission: 'user.view' },
    ].filter((item) => can(item.permission));

    /**
     * Dikelompokkan menurut cara pakainya, bukan urutan alfabetis. Nama tombol
     * dipersingkat supaya seluruh modul muat dalam satu baris di layar kasir
     * yang sering sempit.
     */
    const navGroups = [
        { key: 'catalog', label: 'Katalog', keys: ['categories', 'products', 'customers'] },
        { key: 'money', label: 'Pembayaran', keys: ['methods', 'accounts'] },
        { key: 'access', label: 'Akses', keys: ['roles', 'users'] },
        { key: 'system', label: 'Sistem', keys: ['settings', 'appearance'] },
    ].map((group) => ({
        ...group,
        items: group.keys
            .map((key) => navItems.find((item) => item.key === key))
            .filter(Boolean),
    }));

    // Tab aktif harus selalu milik user ini, mis. setelah role-nya berubah.
    const activeNav = navItems.some((item) => item.key === tab) ? tab : navItems[0]?.key;

    // `activeNav` sudah dideklarasikan di atas: mengacu konstanta yang
    // dideklarasikan belakangan dari dalam callback `find` akan melempar
    // "Cannot access before initialization" dan mematikan seluruh halaman.
    const activeItem = navItems.find((item) => item.key === activeNav);

    useEffect(() => {
        if (activeNav && activeNav !== tab) {
            setTab(activeNav);
        }
    }, [activeNav, tab]);

    // Navigasi admin adalah dropdown pada baris tab: tombolnya menampilkan
    // halaman yang sedang aktif dan membuka daftar modul yang dikelompokkan.
    const header = {
        showCatalog: false,
    };

    const subtitle =
        activeItem?.count !== undefined
            ? `${activeItem.count} data tersimpan`
            : activeItem?.key === 'roles'
              ? 'Atur siapa boleh melakukan apa'
              : activeItem?.key === 'users'
                ? 'Akun yang bisa masuk ke POS'
                : activeItem?.key === 'appearance'
                  ? 'Mode dan warna aplikasi'
                  : 'Perilaku antarmuka kasir';

    return (
        <Layout header={header}>
            <div className="border-b border-line pb-3 mb-4 flex items-center justify-between gap-3 flex-wrap bg-page">
                <AdminNavDropdown groups={navGroups} active={activeNav} onChange={setTab} />
                {activeItem && <p className="text-xs text-muted">{subtitle}</p>}
            </div>

            {activeNav === 'settings' && (
                <div className="space-y-4">
                    <div className="card">
                        <p className="text-sm text-muted mb-5">
                            Atur elemen yang tampil dan aktif pada antarmuka kasir. Perubahan langsung berlaku.
                        </p>
                        <div className="space-y-4">
                            {FLAG_META.map((item) => {
                                const checked = Boolean(flags[item.key]);
                                return (
                                    <div key={item.key} className="flex items-start gap-3">
                                        <span className="w-8 h-8 rounded-lg bg-surface-3 text-muted flex items-center justify-center shrink-0">
                                            <item.icon size={15} />
                                        </span>
                                        <div className="flex-1 min-w-0">
                                            <div className="text-sm font-semibold">{item.label}</div>
                                            <div className="text-xs text-muted">{item.desc}</div>
                                        </div>
                                        <Toggle checked={checked} onChange={() => toggleFlag(item.key, !checked)} />
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}

            {activeNav === 'appearance' && (
                <div className="space-y-4">
                    <div className="card">
                        <p className="text-sm text-muted mb-5">
                            Pilih tampilan terang atau gelap untuk seluruh aplikasi. Mode <span className="font-semibold">Sistem</span> mengikuti pengaturan perangkat kasir.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {THEME_MODES.map((mode) => {
                                const active = appearance.mode === mode.key;
                                return (
                                    <button
                                        key={mode.key}
                                        onClick={() => applyAppearance({ mode: mode.key })}
                                        className={`rounded-xl p-4 text-left transition-colors cursor-pointer ${
                                            active ? 'bg-accent-soft' : 'bg-surface-2 hover:bg-surface-3'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-bold">{mode.label}</span>
                                            {active && <Check size={15} className="text-accent" />}
                                        </div>
                                        <div className="text-xs text-muted mt-1">{mode.hint}</div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="card mt-4">
                        <p className="text-sm text-muted mb-5">Warna utama tombol, harga, dan penanda aktif pada seluruh halaman.</p>
                        <div className="flex flex-wrap gap-3">
                            {THEME_ACCENTS.map((accent) => {
                                const active = appearance.accent === accent.key;
                                return (
                                    <button
                                        key={accent.key}
                                        onClick={() => applyAppearance({ accent: accent.key })}
                                        title={accent.label}
                                        className={`flex items-center gap-2.5 pl-2 pr-4 py-2 rounded-full transition-colors cursor-pointer ${
                                            active ? 'bg-accent-soft' : 'bg-surface-2 hover:bg-surface-3'
                                        }`}
                                    >
                                        <span className="w-7 h-7 rounded-full flex items-center justify-center" style={{ backgroundColor: accent.swatch }}>
                                            {active && <Check size={15} className="text-white" strokeWidth={3} />}
                                        </span>
                                        <span className="text-sm font-semibold">{accent.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <p className="text-xs text-muted mt-4">
                            Pengaturan ini menjadi default aplikasi. Setiap pengguna tetap dapat mengubahnya sendiri lewat ikon palet di header.
                        </p>
                    </div>
                </div>
            )}

            {activeNav === 'categories' && <CategoryManager />}

{activeNav === 'products' && <ProductManager />}

            {activeNav === 'customers' && <CustomerManager />}

            {activeNav === 'roles' && <RoleManager />}

            {activeNav === 'users' && <UserManager />}

            {activeNav === 'methods' && (
                <div className="space-y-4">
                    <div className="card">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-bold flex items-center gap-2"><CreditCard size={17} /> Metode Pembayaran</h3>
                                <p className="text-sm text-muted">Metode pembayaran kas & bank yang tersedia untuk transaksi kasir.</p>
                            </div>
                            <button className="btn btn-primary" onClick={() => openMethodModal()}>
                                <Plus size={16} /> Tambah Metode
                            </button>
                        </div>

                        {methods.length === 0 ? (
                            <p className="text-muted text-sm text-center py-8">Belum ada metode pembayaran.</p>
                        ) : (
                            <div className="border border-line rounded-xl overflow-hidden bg-surface">
                                <table className="w-full">
                                    <thead className="border-b border-line">
                                        <tr>
                                            <th className="table-head">Metode</th>
                                            <th className="table-head">Jenis</th>
                                            <th className="table-head">Akun</th>
                                            <th className="table-head text-center">Aktif</th>
                                            <th className="table-head text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {methods.map((method) => {
                                            const meta = METHOD_TYPE_META[method.type] ?? METHOD_TYPE_META.kas;
                                            const linked = method.accounts ?? [];
                                            const defaultAccount = linked.find((a) => a.is_default) ?? linked[0];
                                            return (
                                                <tr key={method.id}>
                                                    <td className="table-cell">
                                                        <div className="flex items-center gap-2">
                                                            <span className={`w-8 h-8 rounded-lg flex items-center justify-center ${meta.soft}`}>
                                                                <meta.icon size={15} />
                                                            </span>
                                                            <div className="min-w-0">
                                                                <div className="font-semibold text-sm truncate">{method.name}</div>
                                                                <div className="text-[11px] text-muted">{method.code}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="table-cell text-sm capitalize">{meta.label}</td>
                                                    <td className="table-cell text-sm text-muted">
                                                        {linked.length === 0 ? (
                                                            '-'
                                                        ) : defaultAccount ? (
                                                            <span>{defaultAccount.name}</span>
                                                        ) : (
                                                            <span>{linked.length} akun</span>
                                                        )}
                                                    </td>
                                                    <td className="table-cell text-center">
                                                        {method.is_active ? <span className="badge badge-done">Aktif</span> : <span className="badge badge-pending">Nonaktif</span>}
                                                    </td>
                                                    <td className="table-cell">
                                                        <div className="flex justify-end gap-1">
                                                            <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => openMethodModal(method)} title="Edit">
                                                                <Pencil size={14} />
                                                            </button>
                                                            <button className="btn btn-ghost !px-2 !py-1.5 text-negative" onClick={() => removeMethod(method)} title="Hapus">
                                                                <Trash2 size={14} />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {activeNav === 'accounts' && (
                <div className="space-y-4">
                    <div className="card">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-bold flex items-center gap-2"><Wallet size={17} /> Daftar Akun Kas & Bank</h3>
                                <p className="text-sm text-muted">Akun kas & bank yang tersedia untuk transaksi, diinput manual oleh admin.</p>
                                <p className="text-xs text-muted mt-1">Satu rekening default per metode pembayaran dipakai otomatis oleh kasir.</p>
                            </div>
                            <button className="btn btn-primary" onClick={() => openModal()}>
                                <Plus size={16} /> Tambah Akun
                            </button>
                        </div>

                        {accounts.length === 0 ? (
                            <p className="text-muted text-sm text-center py-8">Belum ada akun. Tambahkan akun kas atau bank pertama.</p>
                        ) : (
                            <div className="border border-line rounded-xl overflow-hidden bg-surface">
                                <table className="w-full">
                                    <thead className="border-b border-line">
                                        <tr>
                                            <th className="table-head">Akun</th>
                                            <th className="table-head">Tipe</th>
                                            <th className="table-head">Metode</th>
                                            <th className="table-head">No. Rekening</th>
                                            <th className="table-head text-right">Saldo</th>
                                            <th className="table-head text-center">Aktif</th>
                                            <th className="table-head text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {accounts.map((account) => (
                                            <tr key={account.id}>
                                                <td className="table-cell">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`w-8 h-8 rounded-lg flex items-center justify-center ${account.type === 'bank' ? 'bg-blue-500/10 text-blue-700 dark:text-blue-300' : 'bg-emerald-500/10 text-positive'}`}>
                                                            {account.type === 'bank' ? <Landmark size={15} /> : <Banknote size={15} />}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold text-sm truncate flex items-center gap-1.5">
                                                                {account.name}
                                                                {account.is_default && <span className="text-[10px] font-bold text-accent">DEFAULT</span>}
                                                            </div>
                                                            {account.bank_name && <div className="text-[11px] text-muted">{account.bank_name}</div>}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="table-cell text-sm capitalize">{account.type}</td>
                                                <td className="table-cell text-sm text-muted">{account.payment_method?.name ?? 'Tanpa metode'}</td>
                                                <td className="table-cell text-sm text-muted">{account.account_number ?? '-'}</td>
                                                <td className="table-cell text-right font-semibold text-positive whitespace-nowrap">{formatIDR(account.balance ?? 0)}</td>
                                                <td className="table-cell text-center">
                                                    {account.is_active ? <span className="badge badge-done">Aktif</span> : <span className="badge badge-pending">Nonaktif</span>}
                                                </td>
                                                <td className="table-cell">
                                                    <div className="flex justify-end gap-1">
                                                        {account.payment_method_id && !account.is_default && (
                                                            <button
                                                                className="btn btn-ghost !px-2 !py-1.5"
                                                                onClick={() => makeDefaultAccount.mutate(account)}
                                                                disabled={makeDefaultAccount.isPending}
                                                                title="Jadikan rekening default (otomatis dipakai kasir)"
                                                            >
                                                                <Star size={14} className="text-accent" fill="currentColor" />
                                                            </button>
                                                        )}
                                                        <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => openMutations(account)} title="Lihat mutasi">
                                                            <History size={14} />
                                                        </button>
                                                        <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => openModal(account)} title="Edit">
                                                            <Pencil size={14} />
                                                        </button>
                                                        <button className="btn btn-ghost !px-2 !py-1.5 text-negative" onClick={() => removeAccount(account)} title="Hapus">
                                                            <Trash2 size={14} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {modal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={() => setModal(null)}>
                    <div className="bg-surface rounded-2xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold">{modal === 'edit' ? 'Edit Akun' : 'Tambah Akun'}</h3>
                            <button className="btn-icon w-8 h-8 text-muted hover:bg-surface-3" onClick={() => setModal(null)}>
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="label">Nama Akun</label>
                                <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="mis. Kas Kecil / Bank BCA" />
                            </div>
                            <div>
                                <label className="label">Tipe</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        onClick={() => setForm({ ...form, type: 'kas' })}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${form.type === 'kas' ? 'bg-emerald-600 text-white' : 'bg-surface-3 text-muted hover:bg-surface-3'}`}
                                    >
                                        Kas
                                    </button>
                                    <button
                                        onClick={() => setForm({ ...form, type: 'bank' })}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${form.type === 'bank' ? 'bg-blue-600 text-white' : 'bg-surface-3 text-muted hover:bg-surface-3'}`}
                                    >
                                        Bank
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label className="label">Metode Pembayaran</label>
                                <select
                                    className="select"
                                    value={form.payment_method_id}
                                    onChange={(e) => setForm({ ...form, payment_method_id: e.target.value })}
                                >
                                    <option value="">Tanpa metode (tidak tampil di kasir)</option>
                                    {methods.map((m) => (
                                        <option key={m.id} value={m.id}>{m.name}</option>
                                    ))}
                                </select>
                                <p className="text-[11px] text-muted mt-1">Akun akan muncul sebagai sub-opsi metode ini di kasir.</p>
                            </div>
                            <label className={`flex items-center gap-2 text-sm cursor-pointer ${form.payment_method_id ? '' : 'opacity-40 pointer-events-none'}`}>
                                <input
                                    type="checkbox"
                                    checked={form.is_default}
                                    onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
                                    disabled={!form.payment_method_id}
                                    className="w-4 h-4 accent-accent"
                                />
                                <span>
                                    Jadikan rekening default
                                    <span className="block text-[11px] text-muted">Kasir otomatis memakai rekening ini untuk metode terkait.</span>
                                </span>
                            </label>
                            <div>
                                <label className="label">No. Rekening</label>
                                <input className="input" value={form.account_number} onChange={(e) => setForm({ ...form, account_number: e.target.value })} placeholder="Nomor rekening (opsional)" />
                            </div>
                            {form.type === 'bank' && (
                                <div>
                                    <label className="label">Nama Bank</label>
                                    <input className="input" value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} placeholder="mis. BCA, Mandiri, BNI" />
                                </div>
                            )}
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="w-4 h-4 accent-accent" />
                                Aktif
                            </label>
                        </div>

                        <div className="flex justify-end gap-2 mt-5">
                            <button className="btn btn-ghost" onClick={() => setModal(null)}>Batal</button>
                            <button className="btn btn-primary" disabled={!form.name.trim() || saveAccount.isPending} onClick={() => saveAccount.mutate()}>
                                {saveAccount.isPending ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {methodModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={() => setMethodModal(null)}>
                    <div className="bg-surface rounded-2xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold">{methodModal === 'edit' ? 'Edit Metode' : 'Tambah Metode'}</h3>
                            <button className="btn-icon w-8 h-8 text-muted hover:bg-surface-3" onClick={() => setMethodModal(null)}>
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="label">Jenis</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {Object.entries(METHOD_TYPE_META).map(([type, meta]) => (
                                        <button
                                            key={type}
                                            onClick={() => setMethodForm({ ...methodForm, type })}
                                            className={`px-3 py-2 rounded-lg text-sm font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer ${
                                                methodForm.type === type ? `${meta.color} text-white` : 'bg-surface-3 text-muted hover:bg-surface-3'
                                            }`}
                                        >
                                            <meta.icon size={14} /> {meta.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className="label">Nama Metode</label>
                                <input className="input" value={methodForm.name} onChange={(e) => setMethodForm({ ...methodForm, name: e.target.value })} placeholder="mis. Tunai / Transfer BCA" />
                            </div>
                            <div>
                                <label className="label">Kode</label>
                                <input className="input" value={methodForm.code} onChange={(e) => setMethodForm({ ...methodForm, code: e.target.value })} placeholder="mis. cash / bca" />
                            </div>
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" checked={methodForm.is_active} onChange={(e) => setMethodForm({ ...methodForm, is_active: e.target.checked })} className="w-4 h-4 accent-accent" />
                                Aktif
                            </label>
                        </div>

                        <div className="flex justify-end gap-2 mt-5">
                            <button className="btn btn-ghost" onClick={() => setMethodModal(null)}>Batal</button>
                            <button className="btn btn-primary" disabled={!methodForm.name.trim() || !methodForm.code.trim() || saveMethod.isPending} onClick={() => saveMethod.mutate()}>
                                {saveMethod.isPending ? 'Menyimpan...' : 'Simpan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        {mutations && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={() => setMutations(null)}>
                    <div className="bg-surface rounded-2xl w-full max-w-lg max-h-[85vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-5 pt-5 pb-3 border-b border-line">
                            <div>
                                <h3 className="font-bold flex items-center gap-2"><History size={17} /> Mutasi {mutations.account.name}</h3>
                                <p className="text-xs text-muted mt-0.5">Incoming payment sebagai <span className="text-positive font-semibold">saldo {formatIDR(mutations.account.balance ?? 0)}</span></p>
                            </div>
                            <button className="btn-icon w-8 h-8 text-muted hover:bg-surface-3" onClick={() => setMutations(null)}>
                                <X size={18} />
                            </button>
                        </div>

                        <div className="overflow-y-auto px-5 py-4">
                            {mutations.receipts.length === 0 ? (
                                <p className="text-muted text-sm text-center py-8">Belum ada mutasi masuk untuk akun ini.</p>
                            ) : (
                                <table className="w-full">
                                    <thead className="border-b border-line">
                                        <tr>
                                            <th className="table-head !px-2">Faktur</th>
                                            <th className="table-head !px-2">Tanggal</th>
                                            <th className="table-head !px-2">Metode</th>
                                            <th className="table-head !px-2 text-right">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {mutations.receipts.map((receipt) => (
                                            <tr key={receipt.id}>
                                                <td className="table-cell !px-2">
                                                    <div className="font-semibold text-xs">{receipt.invoice?.invoice_number ?? '-'}</div>
                                                    <div className="text-[11px] text-muted">{receipt.invoice?.order?.order_number}</div>
                                                </td>
                                                <td className="table-cell !px-2 text-xs text-muted">
                                                    {new Date(receipt.payment_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                                </td>
                                                <td className="table-cell !px-2 text-xs">{receipt.payment_method}</td>
                                                <td className="table-cell !px-2 text-right font-semibold text-positive">{formatIDR(receipt.net_amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>

                        <div className="px-5 py-3 border-t border-line flex items-center justify-between">
                            <span className="text-sm font-bold">Total Masuk</span>
                            <span className="text-sm font-bold text-positive">
                                {formatIDR(mutations.receipts.reduce((sum, r) => sum + Number(r.net_amount), 0))}
                            </span>
                        </div>
                    </div>
                </div>
            )}
        </Layout>
    );
}

function Toggle({ checked, onChange }) {
    return (
        <button
            role="switch"
            aria-checked={checked}
            onClick={onChange}
            aria-label="Toggle"
            className={`relative w-11 h-6 rounded-full transition-colors shrink-0 ${checked ? 'bg-accent' : 'bg-surface-3'}`}
        >
            <span
                className={`absolute top-1 left-1 w-4 h-4 rounded-full bg-surface transition-transform ${checked ? 'translate-x-5' : 'translate-x-0'}`}
            />
        </button>
    );
}