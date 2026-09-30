import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, KeyRound, Pencil, Plus, Search, Trash2, User } from 'lucide-react';
import { api } from '../../api/client';
import { useAuth } from '../../auth/AuthContext';
import { confirmAction, notifyError, notifySuccess } from '../../utils/alerts';
import FormModal, { Field } from './FormModal';

const toForm = (record) => ({
    id: record.id,
    name: record.name ?? '',
    email: record.email ?? '',
    role_ids: record.roles?.map((role) => String(role.id)) ?? [],
    is_active: record.is_active ?? true,
    password: '',
    password_confirmation: '',
});

const formatDateTime = (value) => {
    if (!value) {
        return 'Belum pernah';
    }

    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

export default function UserManager() {
    const queryClient = useQueryClient();
    const { can, user: currentUser } = useAuth();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [roleSlug, setRoleSlug] = useState('');
    const [page, setPage] = useState(1);
    const [form, setForm] = useState(null);
    const [passwordForm, setPasswordForm] = useState(null);
    const [errors, setErrors] = useState({});

    const { data: response, isLoading } = useQuery({
        queryKey: ['admin-users', search, status, roleSlug, page],
        queryFn: async () =>
            (await api.get('/admin/users', {
                params: {
                    search: search || undefined,
                    status: status || undefined,
                    role: roleSlug || undefined,
                    page,
                },
            })).data,
    });

    const { data: roles = [] } = useQuery({
        queryKey: ['admin-roles'],
        queryFn: async () => (await api.get('/admin/roles')).data.data,
        enabled: can('user.view', 'user.create', 'user.update'),
    });

    const users = response?.data ?? [];
    const meta = response?.meta ?? { total: 0, current_page: 1, last_page: 1 };

    // Hanya Super Admin yang boleh memberikan role Super Admin, jadi pilihan itu
    // disembunyikan bagi yang lain agar tidak mendapat error saat menyimpan.
    const assignableRoles = roles.filter(
        (role) => role.is_active && (role.is_super_admin ? currentUser?.is_super_admin : true),
    );

    const saveUser = useMutation({
        mutationFn: (payload) =>
            payload.id
                ? api.put(`/admin/users/${payload.id}`, payload)
                : api.post('/admin/users', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-users'] });
            setForm(null);
            notifySuccess('User tersimpan.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'User tidak dapat disimpan.');
        },
    });

    const resetPassword = useMutation({
        mutationFn: ({ id, ...payload }) => api.post(`/admin/users/${id}/reset-password`, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-users'] });
            setPasswordForm(null);
            notifySuccess('Password diperbarui.', 'Semua sesi user ini dikeluarkan.');
        },
        onError: (error) => {
            setErrors(error.response?.data?.errors ?? {});
            notifyError('Gagal', error.response?.data?.message ?? 'Password tidak dapat diperbarui.');
        },
    });

    const removeUser = async (record) => {
        const confirmed = await confirmAction(
            `Hapus user "${record.name}"?`,
            'Tindakan ini tidak dapat dibatalkan.',
            'Ya, hapus',
        );

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/admin/users/${record.id}`);
            queryClient.invalidateQueries({ queryKey: ['admin-users'] });
            notifySuccess('User dihapus.');
        } catch (error) {
            notifyError('Gagal', error.response?.data?.message ?? 'User tidak dapat dihapus.');
        }
    };

    const openForm = (record = null) => {
        setErrors({});
        setForm(toForm(record ?? { id: null }));
    };

    useEffect(() => {
        setPage(1);
    }, [search, status, roleSlug]);

    const toggleRole = (roleId) =>
        setForm((current) => ({
            ...current,
            role_ids: current.role_ids.includes(roleId)
                ? current.role_ids.filter((id) => id !== roleId)
                : [...current.role_ids, roleId],
        }));

    const submitForm = () => {
        const payload = {
            name: form.name,
            email: form.email,
            role_ids: form.role_ids.map(Number),
            is_active: form.is_active,
        };

        saveUser.mutate(
            form.id
                ? { id: form.id, ...payload }
                : { ...payload, password: form.password, password_confirmation: form.password_confirmation },
        );
    };

    const canSubmit = form
        ? form.name.trim() &&
          form.email.trim() &&
          (form.id || (form.password.length >= 8 && form.password === form.password_confirmation))
        : false;

    const canReset =
        passwordForm && passwordForm.password.length >= 8 && passwordForm.password === passwordForm.password_confirmation;

    return (
        <div className="space-y-4">
            <div className="card">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 className="font-bold flex items-center gap-2"><User size={17} /> User</h3>
                        <p className="text-sm text-muted mt-0.5">
                            Akun yang bisa masuk ke panel admin beserta kelompok hak aksesnya.
                        </p>
                    </div>
                    {can('user.create') && (
                        <button className="btn btn-primary" onClick={() => openForm()}>
                            <Plus size={16} /> Tambah User
                        </button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative flex-1 min-w-[200px] max-w-sm">
                        <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            className="input !pl-9"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama atau email..."
                        />
                    </div>
                    <select
                        className="select !w-auto min-w-[150px]"
                        value={roleSlug}
                        onChange={(event) => setRoleSlug(event.target.value)}
                    >
                        <option value="">Semua role</option>
                        {roles.map((role) => (
                            <option key={role.id} value={role.slug}>{role.name}</option>
                        ))}
                    </select>
                    <select
                        className="select !w-auto min-w-[140px]"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div className="card">
                {isLoading ? (
                    <p className="text-muted text-sm text-center py-8">Memuat user...</p>
                ) : users.length === 0 ? (
                    <p className="text-muted text-sm text-center py-8">Belum ada user yang cocok.</p>
                ) : (
                    <div className="border border-line rounded-xl overflow-hidden bg-surface">
                        <table className="w-full">
                            <thead className="border-b border-line">
                                <tr>
                                    <th className="table-head">User</th>
                                    <th className="table-head">Role</th>
                                    <th className="table-head">Login Terakhir</th>
                                    <th className="table-head text-center">Status</th>
                                    <th className="table-head text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {users.map((record) => {
                                    const isSelf = record.id === currentUser?.id;

                                    return (
                                        <tr key={record.id}>
                                            <td className="table-cell">
                                                <div className="font-semibold text-sm truncate">
                                                    {record.name}
                                                    {isSelf && <span className="text-[11px] text-muted font-normal"> (Anda)</span>}
                                                </div>
                                                <div className="text-[11px] text-muted truncate">{record.email}</div>
                                            </td>
                                            <td className="table-cell">
                                                <div className="flex flex-wrap gap-1">
                                                    {record.roles.length === 0 ? (
                                                        <span className="text-[11px] text-faint">Tanpa role</span>
                                                    ) : (
                                                        record.roles.map((role) => (
                                                            <span key={role.id} className="badge badge-accent">{role.name}</span>
                                                        ))
                                                    )}
                                                </div>
                                            </td>
                                            <td className="table-cell text-[11px] text-muted whitespace-nowrap">
                                                {formatDateTime(record.last_login_at)}
                                            </td>
                                            <td className="table-cell text-center">
                                                <span className={`badge ${record.is_active ? 'badge-paid' : 'badge-pending'}`}>
                                                    {record.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="table-cell">
                                                <div className="flex items-center justify-end gap-1">
                                                    {can('user.update') && (
                                                        <>
                                                            <button
                                                                className="btn btn-ghost"
                                                                title="Reset password"
                                                                onClick={() => {
                                                                    setErrors({});
                                                                    setPasswordForm({
                                                                        id: record.id,
                                                                        name: record.name,
                                                                        password: '',
                                                                        password_confirmation: '',
                                                                    });
                                                                }}
                                                            >
                                                                <KeyRound size={14} />
                                                            </button>
                                                            <button
                                                                className="btn btn-ghost"
                                                                title="Edit user"
                                                                onClick={() => openForm(record)}
                                                            >
                                                                <Pencil size={14} />
                                                            </button>
                                                        </>
                                                    )}
                                                    {can('user.delete') && (
                                                        <button
                                                            className="btn btn-ghost"
                                                            title={isSelf ? 'Anda tidak dapat menghapus akun sendiri' : 'Hapus user'}
                                                            onClick={() => removeUser(record)}
                                                            disabled={isSelf}
                                                        >
                                                            <Trash2 size={14} />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex items-center justify-between gap-3 mt-4">
                    <p className="text-[11px] text-muted">{meta.total} user</p>
                    {meta.last_page > 1 && (
                        <div className="flex items-center gap-2">
                            <button
                                className="btn btn-ghost"
                                disabled={meta.current_page <= 1}
                                onClick={() => setPage((current) => current - 1)}
                            >
                                <ChevronLeft size={14} /> Sebelumnya
                            </button>
                            <span className="text-[11px] text-muted">
                                {meta.current_page} / {meta.last_page}
                            </span>
                            <button
                                className="btn btn-ghost"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => setPage((current) => current + 1)}
                            >
                                Berikutnya <ChevronRight size={14} />
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {form && (
                <FormModal
                    title={form.id ? `Edit ${form.name}` : 'Tambah User'}
                    subtitle="Role menentukan modul mana yang bisa diakses user."
                    onClose={() => setForm(null)}
                    onSubmit={submitForm}
                    submitLabel={form.id ? 'Simpan Perubahan' : 'Simpan User'}
                    pending={saveUser.isPending}
                    disabled={!canSubmit}
                    width="max-w-lg"
                >
                    <div className="space-y-4">
                        <Field label="Nama Lengkap" error={errors.name?.[0]}>
                            <input
                                className="input"
                                value={form.name}
                                onChange={(event) => setForm({ ...form, name: event.target.value })}
                            />
                        </Field>

                        <Field label="Email" error={errors.email?.[0]}>
                            <input
                                className="input"
                                type="email"
                                value={form.email}
                                onChange={(event) => setForm({ ...form, email: event.target.value })}
                            />
                        </Field>

                        {!form.id && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Field label="Password" error={errors.password?.[0]} hint="Minimal 8 karakter.">
                                    <input
                                        className="input"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.password}
                                        onChange={(event) => setForm({ ...form, password: event.target.value })}
                                    />
                                </Field>
                                <Field
                                    label="Konfirmasi Password"
                                    error={
                                        form.password_confirmation && form.password !== form.password_confirmation
                                            ? 'Password tidak sama.'
                                            : null
                                    }
                                >
                                    <input
                                        className="input"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.password_confirmation}
                                        onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
                                    />
                                </Field>
                            </div>
                        )}

                        <div>
                            <p className="label">Kelompok Hak Akses</p>
                            {assignableRoles.length === 0 ? (
                                <p className="text-[11px] text-muted">Belum ada role aktif.</p>
                            ) : (
                                <div className="border border-line rounded-xl divide-y divide-line max-h-52 overflow-y-auto">
                                    {assignableRoles.map((role) => (
                                        <label
                                            key={role.id}
                                            className="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                className="accent-[var(--accent)]"
                                                checked={form.role_ids.includes(String(role.id))}
                                                onChange={() => toggleRole(String(role.id))}
                                            />
                                            <span className="flex-1">{role.name}</span>
                                            {role.is_super_admin && <span className="badge badge-paid">Super Admin</span>}
                                        </label>
                                    ))}
                                </div>
                            )}
                            {errors['role_ids.0']?.[0] && (
                                <p className="text-[11px] text-negative mt-1">{errors['role_ids.0'][0]}</p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="accent-[var(--accent)]"
                                checked={form.is_active}
                                disabled={form.id === currentUser?.id}
                                onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                            />
                            Aktifkan user
                        </label>

                        {form.id === currentUser?.id && (
                            <p className="text-[11px] text-muted">Anda tidak dapat menonaktifkan akun sendiri.</p>
                        )}
                    </div>
                </FormModal>
            )}

            {passwordForm && (
                <FormModal
                    title={`Reset Password ${passwordForm.name}`}
                    subtitle="Semua sesi aktif user ini akan dikeluarkan."
                    onClose={() => setPasswordForm(null)}
                    onSubmit={() => resetPassword.mutate(passwordForm)}
                    submitLabel="Perbarui Password"
                    pending={resetPassword.isPending}
                    disabled={!canReset}
                >
                    <div className="space-y-4">
                        <Field label="Password Baru" error={errors.password?.[0]} hint="Minimal 8 karakter.">
                            <input
                                className="input"
                                type="password"
                                autoComplete="new-password"
                                value={passwordForm.password}
                                onChange={(event) => setPasswordForm({ ...passwordForm, password: event.target.value })}
                            />
                        </Field>
                        <Field
                            label="Konfirmasi Password"
                            error={
                                passwordForm.password_confirmation &&
                                passwordForm.password !== passwordForm.password_confirmation
                                    ? 'Password tidak sama.'
                                    : null
                            }
                        >
                            <input
                                className="input"
                                type="password"
                                autoComplete="new-password"
                                value={passwordForm.password_confirmation}
                                onChange={(event) =>
                                    setPasswordForm({ ...passwordForm, password_confirmation: event.target.value })
                                }
                            />
                        </Field>
                    </div>
                </FormModal>
            )}
        </div>
    );
}
