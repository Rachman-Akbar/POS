import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Wallet, Pencil, Trash2, Plus, X, Landmark, Banknote, Settings2, Star, Package, Hash, Percent, CreditCard, QrCode,
} from 'lucide-react';
import Layout from '../components/Layout';
import { api, formatIDR } from '../api/client';
import { notifySuccess, notifyError, confirmAction } from '../utils/alerts';

const FLAG_META = [
    { key: 'cashier_show_favorites', label: 'Produk Favorit', icon: Star, desc: 'Tampilkan atau sembunyikan bagian produk favorit di katalog kasir.' },
    { key: 'cashier_show_stock', label: 'Stok Produk', icon: Package, desc: 'Tampilkan atau sembunyikan jumlah stok pada kartu produk.' },
    { key: 'cashier_enable_table', label: 'Input Nomor Meja', icon: Hash, desc: 'Aktifkan kewajiban memilih nomor meja saat transaksi.' },
    { key: 'cashier_enable_ppn', label: 'Penerapan PPN', icon: Percent, desc: 'Terapkan pajak PPN pada setiap transaksi kasir.' },
    { key: 'cashier_enable_prepay', label: 'Sistem Bayar Di Muka', icon: Wallet, desc: 'Izinkan status pembayaran di muka (seluruh uang diterima sebelum transaksi disimpan).' },
];

const EMPTY_FORM = { name: '', type: 'kas', account_number: '', bank_name: '', is_active: true };
const EMPTY_METHOD = { code: '', type: 'kas', name: '', is_active: true };

export default function AdminDashboard() {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState('settings');
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState(EMPTY_FORM);
    const [methodModal, setMethodModal] = useState(null);
    const [methodForm, setMethodForm] = useState(EMPTY_METHOD);

    const { data: flags = {} } = useQuery({
        queryKey: ['admin-flags'],
        queryFn: async () => (await api.get('/admin/settings')).data.data,
    });

    const { data: accounts = [] } = useQuery({
        queryKey: ['cash-bank-accounts'],
        queryFn: async () => (await api.get('/cash-bank-accounts')).data.data,
    });

    const { data: products = [] } = useQuery({
        queryKey: ['admin-products'],
        queryFn: async () => (await api.get('/products')).data.data,
    });

    const { data: methods = [] } = useQuery({
        queryKey: ['payment-methods'],
        queryFn: async () => (await api.get('/payment-methods')).data.data,
    });

    const toggleProductFavorite = useMutation({
        mutationFn: (product) => api.patch(`/products/${product.id}/favorite`),
        onMutate: (product) => {
            queryClient.setQueryData(['admin-products'], (old) =>
                old?.map((p) => (p.id === product.id ? { ...p, is_favorite: !p.is_favorite } : p)),
            );
        },
        onSettled: () => queryClient.invalidateQueries({ queryKey: ['admin-products'] }),
    });

    const saveFlags = useMutation({
        mutationFn: (toSave) => api.put('/admin/settings', { flags: toSave }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-flags'] });
            queryClient.invalidateQueries({ queryKey: ['settings'] });
        },
        onError: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-flags'] });
            notifyError('Gagal', 'Pengaturan tidak dapat disimpan.');
        },
    });

    const saveAccount = useMutation({
        mutationFn: async () => {
            const payload = {
                name: form.name,
                type: form.type,
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
        queryClient.setQueryData(['admin-flags'], next);
        saveFlags.mutate(next);
    };

    const openModal = (account = null) => {
        setForm(
            account
                ? { id: account.id, name: account.name, type: account.type, account_number: account.account_number ?? '', bank_name: account.bank_name ?? '', is_active: account.is_active }
                : EMPTY_FORM,
        );
        setModal(account ? 'edit' : 'add');
    };

    const header = {
        navLabel: 'Menu Admin',
        navItems: [
            { key: 'settings', label: 'Pengaturan Kasir', icon: Settings2 },
            { key: 'favorites', label: 'Produk Favorit', icon: Star },
            { key: 'methods', label: 'Metode Pembayaran', icon: CreditCard, count: methods.length },
            { key: 'accounts', label: 'Kas & Bank', icon: Wallet, count: accounts.length },
        ],
        activeNav: tab,
        onNavChange: setTab,
    };

    return (
        <Layout header={header}>
            {tab === 'settings' && (
                <div className="max-w-2xl space-y-4">
                    <div className="card">
                        <h3 className="font-bold mb-1 flex items-center gap-2"><Settings2 size={17} /> Pengaturan Tampilan Kasir</h3>
                        <p className="text-sm text-muted mb-5">Atur elemen yang tampil dan aktif pada antarmuka kasir. Perubahan langsung berlaku.</p>
                        <div className="space-y-4">
                            {FLAG_META.map((item) => {
                                const checked = Boolean(flags[item.key]);
                                return (
                                    <div key={item.key} className="flex items-start gap-3">
                                        <span className="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center shrink-0">
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

            {tab === 'favorites' && (
                <div className="max-w-3xl space-y-4">
                    <div className="card">
                        <div className="mb-4">
                            <h3 className="font-bold">Produk Favorit</h3>
                            <p className="text-sm text-muted">Tandai produk yang tampil pada container Favorit di katalog kasir.</p>
                        </div>

                        {products.length === 0 ? (
                            <p className="text-muted text-sm text-center py-8">Belum ada produk aktif.</p>
                        ) : (
                            <div className="border border-gray-100 rounded-xl overflow-hidden">
                                <table className="w-full">
                                    <thead className="bg-gray-50/70">
                                        <tr>
                                            <th className="table-head">Produk</th>
                                            <th className="table-head">Kategori</th>
                                            <th className="table-head text-right">Harga</th>
                                            <th className="table-head text-center">Favorit</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {products.map((product) => (
                                            <tr key={product.id}>
                                                <td className="table-cell">
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        <div className="w-9 h-9 rounded-lg overflow-hidden shrink-0 bg-gray-50 border border-gray-100">
                                                            {product.image ? (
                                                                <img src={product.image} alt={product.name} className="w-full h-full object-cover" />
                                                            ) : (
                                                                <Package size={16} className="w-full h-full m-auto text-gray-300" />
                                                            )}
                                                        </div>
                                                        <span className="font-semibold text-sm truncate">{product.name}</span>
                                                    </div>
                                                </td>
                                                <td className="table-cell text-sm text-muted">{product.category ?? 'Lainnya'}</td>
                                                <td className="table-cell text-right text-orange-600 font-semibold">{formatIDR(product.price)}</td>
                                                <td className="table-cell text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={!!product.is_favorite}
                                                        onChange={() => toggleProductFavorite.mutate(product)}
                                                        title={product.is_favorite ? 'Hapus dari favorit' : 'Jadikan favorit'}
                                                        className="w-4 h-4 accent-orange-600 cursor-pointer align-middle"
                                                    />
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

            {tab === 'methods' && (
                <div className="max-w-3xl space-y-4">
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
                            <div className="border border-gray-100 rounded-xl overflow-hidden">
                                <table className="w-full">
                                    <thead className="bg-gray-50/70">
                                        <tr>
                                            <th className="table-head">Metode</th>
                                            <th className="table-head">Jenis</th>
                                            <th className="table-head text-center">Aktif</th>
                                            <th className="table-head text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {methods.map((method) => (
                                            <tr key={method.id}>
                                                <td className="table-cell">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`w-8 h-8 rounded-lg flex items-center justify-center ${method.type === 'bank' ? 'bg-blue-50 text-blue-700' : method.type === 'qris' ? 'bg-purple-50 text-purple-700' : 'bg-emerald-50 text-emerald-700'}`}>
                                                            {method.type === 'bank' ? <Landmark size={15} /> : method.type === 'qris' ? <QrCode size={15} /> : <Banknote size={15} />}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold text-sm truncate">{method.name}</div>
                                                            <div className="text-[11px] text-muted">{method.code}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="table-cell text-sm capitalize">{method.type}</td>
                                                <td className="table-cell text-center">
                                                    {method.is_active ? <span className="badge badge-done">Aktif</span> : <span className="badge badge-pending">Nonaktif</span>}
                                                </td>
                                                <td className="table-cell">
                                                    <div className="flex justify-end gap-1">
                                                        <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => openMethodModal(method)} title="Edit">
                                                            <Pencil size={14} />
                                                        </button>
                                                        <button className="btn btn-ghost !px-2 !py-1.5 text-red-500" onClick={() => removeMethod(method)} title="Hapus">
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

            {tab === 'accounts' && (
                <div className="max-w-3xl space-y-4">
                    <div className="card">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-bold flex items-center gap-2"><Wallet size={17} /> Daftar Akun Kas & Bank</h3>
                                <p className="text-sm text-muted">Akun kas & bank yang tersedia untuk transaksi, diinput manual oleh admin.</p>
                            </div>
                            <button className="btn btn-primary" onClick={() => openModal()}>
                                <Plus size={16} /> Tambah Akun
                            </button>
                        </div>

                        {accounts.length === 0 ? (
                            <p className="text-muted text-sm text-center py-8">Belum ada akun. Tambahkan akun kas atau bank pertama.</p>
                        ) : (
                            <div className="border border-gray-100 rounded-xl overflow-hidden">
                                <table className="w-full">
                                    <thead className="bg-gray-50/70">
                                        <tr>
                                            <th className="table-head">Akun</th>
                                            <th className="table-head">Tipe</th>
                                            <th className="table-head">No. Rekening</th>
                                            <th className="table-head text-center">Aktif</th>
                                            <th className="table-head text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {accounts.map((account) => (
                                            <tr key={account.id}>
                                                <td className="table-cell">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`w-8 h-8 rounded-lg flex items-center justify-center ${account.type === 'bank' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'}`}>
                                                            {account.type === 'bank' ? <Landmark size={15} /> : <Banknote size={15} />}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="font-semibold text-sm truncate">{account.name}</div>
                                                            {account.bank_name && <div className="text-[11px] text-muted">{account.bank_name}</div>}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="table-cell text-sm capitalize">{account.type}</td>
                                                <td className="table-cell text-sm text-muted">{account.account_number ?? '-'}</td>
                                                <td className="table-cell text-center">
                                                    {account.is_active ? <span className="badge badge-done">Aktif</span> : <span className="badge badge-pending">Nonaktif</span>}
                                                </td>
                                                <td className="table-cell">
                                                    <div className="flex justify-end gap-1">
                                                        <button className="btn btn-ghost !px-2 !py-1.5" onClick={() => openModal(account)} title="Edit">
                                                            <Pencil size={14} />
                                                        </button>
                                                        <button className="btn btn-ghost !px-2 !py-1.5 text-red-500" onClick={() => removeAccount(account)} title="Hapus">
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
                    <div className="bg-white rounded-2xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold">{modal === 'edit' ? 'Edit Akun' : 'Tambah Akun'}</h3>
                            <button className="btn-icon w-8 h-8 text-muted hover:bg-gray-100" onClick={() => setModal(null)}>
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
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${form.type === 'kas' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-muted hover:bg-gray-200'}`}
                                    >
                                        Kas
                                    </button>
                                    <button
                                        onClick={() => setForm({ ...form, type: 'bank' })}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${form.type === 'bank' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-muted hover:bg-gray-200'}`}
                                    >
                                        Bank
                                    </button>
                                </div>
                            </div>
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
                                <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="w-4 h-4 accent-orange-600" />
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
                    <div className="bg-white rounded-2xl w-full max-w-md p-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold">{methodModal === 'edit' ? 'Edit Metode' : 'Tambah Metode'}</h3>
                            <button className="btn-icon w-8 h-8 text-muted hover:bg-gray-100" onClick={() => setMethodModal(null)}>
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="label">Jenis</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        onClick={() => setMethodForm({ ...methodForm, type: 'kas' })}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${methodForm.type === 'kas' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-muted hover:bg-gray-200'}`}
                                    >
                                        Kas
                                    </button>
                                    <button
                                        onClick={() => setMethodForm({ ...methodForm, type: 'bank' })}
                                        className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors cursor-pointer ${methodForm.type === 'bank' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-muted hover:bg-gray-200'}`}
                                    >
                                        Bank
                                    </button>
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
                                <input type="checkbox" checked={methodForm.is_active} onChange={(e) => setMethodForm({ ...methodForm, is_active: e.target.checked })} className="w-4 h-4 accent-orange-600" />
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
            className={`relative w-11 h-6 rounded-full transition-colors shrink-0 ${checked ? 'bg-orange-600' : 'bg-gray-200'}`}
        >
            <span
                className={`absolute top-1 left-1 w-4 h-4 rounded-full bg-white transition-transform ${checked ? 'translate-x-5' : 'translate-x-0'}`}
            />
        </button>
    );
}