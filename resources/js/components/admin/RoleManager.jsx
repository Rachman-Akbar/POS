import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Pencil, Plus, Search, ShieldCheck, Trash2, Users } from 'lucide-react';
import { api } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { confirmAction, notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field } from './FormModal';

const EMPTY_FORM = { name: '', description: '', is_active: true, permissions: [] };

const toForm = (role) =>
    role
        ? {
              id: role.id,
              name: role.name,
              description: role.description ?? '',
              is_active: Boolean(role.is_active),
              is_super_admin: Boolean(role.is_super_admin),
              permissions: [...role.permissions],
          }
        : { ...EMPTY_FORM, is_super_admin: false };

export default function RoleManager() {
    const queryClient = useQueryClient();
    const { can, user: currentUser } = useAuth();
    const [search, setSearch] = useState('');
    const [permissionSearch, setPermissionSearch] = useState('');
    const [form, setForm] = useState(null);
    const [errors, setErrors] = useState({});

    const { data: roles = [] } = useQuery({
        queryKey: ['admin-roles'],
        queryFn: async () => (await api.get('/admin/roles')).data.data,
    });

    const { data: catalog = [] } = useQuery({
        queryKey: ['admin-permissions'],
        queryFn: async () => (await api.get('/admin/permissions')).data.data,
        enabled: can('role.view'),
    });

    const saveRole = useMutation({
        mutationFn: (payload) =>
            payload.id
                ? api.put(`/admin/roles/${payload.id}`, payload)
                : api.post('/admin/roles', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-roles'] });
            setForm(null);
            notifySuccess('Kelompok hak akses tersimpan.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Kelompok hak akses tidak dapat disimpan.');
        },
    });

    const removeRole = async (role) => {
        const confirmed = await confirmAction(
            `Hapus kelompok hak akses "${role.name}"?`,
            'Role yang sedang dipakai user tidak dapat dihapus.',
            'Ya, hapus',
        );

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/admin/roles/${role.id}`);
            queryClient.invalidateQueries({ queryKey: ['admin-roles'] });
            notifySuccess('Kelompok hak akses dihapus.');
        } catch (error) {
            notifyError('Gagal', error.response?.data?.message ?? 'Kelompok hak akses tidak dapat dihapus.');
        }
    };

    const modules = useMemo(() => {
        const keyword = permissionSearch.trim().toLowerCase();

        if (!keyword) {
            return catalog;
        }

        return catalog
            .map((module) => ({
                ...module,
                permissions: module.permissions.filter(
                    (permission) =>
                        permission.label.toLowerCase().includes(keyword) ||
                        permission.name.toLowerCase().includes(keyword) ||
                        module.label.toLowerCase().includes(keyword),
                ),
            }))
            .filter((module) => module.permissions.length > 0);
    }, [catalog, permissionSearch]);

    const selected = form?.permissions ?? [];

    const togglePermission = (name) => {
        setForm((current) => ({
            ...current,
            permissions: current.permissions.includes(name)
                ? current.permissions.filter((item) => item !== name)
                : [...current.permissions, name],
        }));
    };

    const toggleModule = (module, checked) => {
        const names = module.permissions.map((permission) => permission.name);

        setForm((current) => ({
            ...current,
            permissions: checked
                ? Array.from(new Set([...current.permissions, ...names]))
                : current.permissions.filter((name) => !names.includes(name)),
        }));
    };

    const moduleState = (module) => {
        const names = module.permissions.map((permission) => permission.name);
        const active = names.filter((name) => selected.includes(name)).length;

        return { active, total: names.length, all: active === names.length && names.length > 0 };
    };

    const totalGranted = catalog.reduce((sum, module) => sum + moduleState(module).active, 0);
    const totalAll = catalog.reduce((sum, module) => sum + module.permissions.length, 0);
    const visible = roles.filter((role) => role.name.toLowerCase().includes(search.trim().toLowerCase()));

    const openForm = (role = null) => {
        setErrors({});
        setPermissionSearch('');
        setForm(toForm(role));
    };

    const submitForm = () =>
        saveRole.mutate({
            id: form.id,
            name: form.name,
            description: form.description || null,
            is_active: form.is_active,
            permissions: form.permissions,
        });

    return (
        <div className="space-y-4">
            <div className="card">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 className="font-bold flex items-center gap-2"><ShieldCheck size={17} /> Kelompok Hak Akses</h3>
                        <p className="text-sm text-muted mt-0.5">
                            Tentukan modul yang boleh diakses setiap kelompok. Perubahan berlaku setelah user memuat ulang halaman.
                        </p>
                    </div>
                    {can('role.create') && (
                        <button className="btn btn-primary" onClick={() => openForm()}>
                            <Plus size={16} /> Tambah Role
                        </button>
                    )}
                </div>

                <div className="relative max-w-sm">
                    <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        className="input !pl-9"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari kelompok hak akses..."
                    />
                </div>
            </div>

            <div className="card">
                {visible.length === 0 ? (
                    <p className="text-muted text-sm text-center py-8">Belum ada kelompok hak akses yang cocok.</p>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        {visible.map((role) => (
                            <div key={role.id} className="border border-line rounded-xl p-4 flex flex-col gap-3">
                                <div>
                                    <div className="flex items-start justify-between gap-2">
                                        <h4 className="font-bold text-sm">{role.name}</h4>
                                        {role.is_system && <span className="badge badge-accent shrink-0">Sistem</span>}
                                    </div>
                                    <p className="text-[11px] text-muted mt-1 line-clamp-2">
                                        {role.description || 'Tanpa deskripsi.'}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted">
                                    <span className="inline-flex items-center gap-1">
                                        <Users size={12} /> {role.users_count} user
                                    </span>
                                    <span>·</span>
                                    <span>{role.permissions_count} permission</span>
                                    {!role.is_active && <span className="badge badge-pending">Nonaktif</span>}
                                    {role.is_super_admin && <span className="badge badge-paid">Akses penuh</span>}
                                </div>

                                <div className="flex items-center gap-2 mt-auto pt-1">
                                    {can('role.update') && (
                                        <button
                                            className="btn btn-secondary flex-1 justify-center"
                                            onClick={() => openForm(role)}
                                            // Hanya Super Admin yang boleh mengubah role Super Admin.
                                            disabled={role.is_super_admin && !currentUser?.is_super_admin}
                                            title={
                                                role.is_super_admin && !currentUser?.is_super_admin
                                                    ? 'Hanya Super Admin yang dapat mengubah role ini'
                                                    : undefined
                                            }
                                        >
                                            <Pencil size={14} /> Atur
                                        </button>
                                    )}
                                    {can('role.delete') && (
                                        <button
                                            className="btn btn-ghost"
                                            title={
                                                role.is_system
                                                    ? 'Role sistem tidak dapat dihapus'
                                                    : role.users_count > 0
                                                      ? 'Role masih dipakai user'
                                                      : 'Hapus role'
                                            }
                                            onClick={() => removeRole(role)}
                                            disabled={role.is_system || role.users_count > 0}
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {form && (
                <FormModal
                    title={form.id ? `Atur ${form.name}` : 'Tambah Kelompok Hak Akses'}
                    subtitle="Centang modul yang boleh diakses. Super Admin selalu memiliki akses penuh."
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    submitLabel={form.id ? 'Simpan Perubahan' : 'Simpan Role'}
                    pending={saveRole.isPending}
                    disabled={!form.name.trim()}
                    width="max-w-3xl"
                >
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label="Nama Role" error={errors.name?.[0]}>
                                <input
                                    className="input"
                                    value={form.name}
                                    onChange={(event) => setForm({ ...form, name: event.target.value })}
                                    placeholder="Contoh: Kasir Toko"
                                />
                            </Field>
                            <Field label="Deskripsi" error={errors.description?.[0]}>
                                <input
                                    className="input"
                                    value={form.description}
                                    onChange={(event) => setForm({ ...form, description: event.target.value })}
                                    placeholder="Opsional"
                                />
                            </Field>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="accent-[var(--accent)]"
                                checked={form.is_active}
                                // Backend memaksa role Super Admin tetap aktif.
                                disabled={form.is_super_admin}
                                onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                            />
                            Aktifkan role ini
                        </label>

                        {/* Backend mengabaikan permission untuk role Super Admin, jadi grid
                            ditolak agar user tidak mengira perubahan tersimpan. */}
                        {form.is_super_admin ? (
                            <p className="border border-line rounded-xl px-4 py-6 text-sm text-muted text-center">
                                Role Super Admin memiliki akses ke seluruh modul secara otomatis.
                                Daftar permission tidak perlu diubah.
                            </p>
                        ) : (
                        <div className="border border-line rounded-xl">
                            <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-line">
                                <div className="flex items-center gap-2">
                                    <Search size={14} className="text-faint" />
                                    <input
                                        className="text-sm bg-transparent outline-none flex-1 min-w-[140px]"
                                        value={permissionSearch}
                                        onChange={(event) => setPermissionSearch(event.target.value)}
                                        placeholder="Cari permission..."
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-[11px] text-muted">
                                        {totalGranted}/{totalAll} aktif
                                    </span>
                                    <button
                                        className="text-[11px] font-semibold text-accent-ink hover:underline"
                                        onClick={() =>
                                            setForm((current) => ({
                                                ...current,
                                                permissions: Array.from(
                                                    new Set([...current.permissions, ...modules.flatMap((m) => m.permissions.map((p) => p.name))]),
                                                ),
                                            }))
                                        }
                                    >
                                        Pilih semua
                                    </button>
                                    <button
                                        className="text-[11px] font-semibold text-muted hover:underline"
                                        onClick={() => setForm((current) => ({ ...current, permissions: [] }))}
                                    >
                                        Kosongkan
                                    </button>
                                </div>
                            </div>

                            <div className="divide-y divide-line max-h-[45vh] overflow-y-auto">
                                {modules.length === 0 ? (
                                    <p className="text-muted text-sm text-center py-6">Permission tidak ditemukan.</p>
                                ) : (
                                    modules.map((module) => {
                                        const state = moduleState(module);

                                        return (
                                            <div key={module.key} className="px-4 py-3">
                                                <div className="flex items-center justify-between gap-3 mb-2">
                                                    <h5 className="text-sm font-semibold">{module.label}</h5>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-[11px] text-muted">
                                                            {state.active}/{state.total}
                                                        </span>
                                                        <button
                                                            className="text-[11px] font-semibold text-accent-ink hover:underline"
                                                            onClick={() => toggleModule(module, !state.all)}
                                                        >
                                                            {state.all ? 'Hapus semua' : 'Pilih semua'}
                                                        </button>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5">
                                                    {module.permissions.map((permission) => {
                                                        const checked = selected.includes(permission.name);

                                                        return (
                                                            <label
                                                                key={permission.name}
                                                                className="flex items-center gap-2 text-[13px] cursor-pointer"
                                                                title={permission.name}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    className="accent-[var(--accent)]"
                                                                    checked={checked}
                                                                    onChange={() => togglePermission(permission.name)}
                                                                />
                                                                <span>{permission.label}</span>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                        )}

                        {form.id && !form.is_super_admin && (
                            <p className="text-[11px] text-muted flex items-center gap-1">
                                <Check size={12} /> Simpan untuk menerapkan permission ke seluruh user dengan role ini.
                            </p>
                        )}
                    </div>
                </FormModal>
            )}
        </div>
    );
}
