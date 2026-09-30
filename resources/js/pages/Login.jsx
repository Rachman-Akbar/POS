import { useEffect, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { LogIn, ShieldCheck } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import {
    ADMIN_PERMISSIONS,
    CASHIER_PERMISSIONS,
    KITCHEN_PERMISSIONS,
    WAITER_PERMISSIONS,
    landingFor,
} from '../auth/permissions';
import { errorMessage, retryAfterSeconds } from '../api/client';

/**
 * Login adalah satu-satunya jalan masuk ke POS: katalog, transaksi, dapur, dan
 * pelayan semuanya butuh sesi, jadi kasir dan pelayan pun wajib punya akun.
 */
const PAGE_PERMISSIONS = {
    '/kasir': CASHIER_PERMISSIONS,
    '/koki': KITCHEN_PERMISSIONS,
    '/waiters': WAITER_PERMISSIONS,
    '/admin': ADMIN_PERMISSIONS,
};

/**
 * User diarahkan ke halaman yang benar-benar dia punya aksesnya. Tanpa ini,
 * Dapur yang terus punya `location.from = /kasir` akan mendarat di "Akses
 * ditolak" padahal dia sebenarnya boleh masuk ke halaman lain.
 */
const destinationFor = (user, target) => {
    const owns = (permissions) =>
        Boolean(user?.is_super_admin) || (user?.permissions ?? []).some((name) => permissions.includes(name));

    if (target && owns(PAGE_PERMISSIONS[target] ?? [])) {
        return target;
    }

    return landingFor(user?.permissions) ?? '/login';
};

export default function Login() {
    const { login, user, isAuthenticated } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [form, setForm] = useState({ identity: '', password: '' });
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);
    // Sisa detik sebelum login boleh dicoba lagi, diisi dari `Retry-After`
    // saat backend membalas 429.
    const [lockedFor, setLockedFor] = useState(0);

    const target = location.state?.from ?? null;

    useEffect(() => {
        if (lockedFor <= 0) return undefined;

        const timer = setTimeout(() => setLockedFor((seconds) => seconds - 1), 1000);

        return () => clearTimeout(timer);
    }, [lockedFor]);

    if (isAuthenticated) {
        return <Navigate to={destinationFor(user, target)} replace />;
    }

    const locked = lockedFor > 0;

    const submit = async (event) => {
        event.preventDefault();
        if (locked) return;

        setError(null);
        setPending(true);

        try {
            const loggedIn = await login(form.identity, form.password);

            navigate(destinationFor(loggedIn, target), { replace: true });
        } catch (err) {
            setError(errorMessage(err, 'Kredensial tidak valid.'));
            setLockedFor(retryAfterSeconds(err));
        } finally {
            setPending(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-sm">
                <div className="text-center mb-6">
                    <div className="w-12 h-12 rounded-xl bg-accent text-on-accent flex items-center justify-center mx-auto">
                        <ShieldCheck size={24} />
                    </div>
                    <h1 className="text-xl font-bold mt-3">Masuk ke POS</h1>
                    <p className="text-sm text-muted mt-1">
                        Kasir, dapur, dan pelayan memakai akun yang diberikan admin.
                    </p>
                </div>

                <form className="card space-y-4" onSubmit={submit}>
                    <div>
                        <label className="label" htmlFor="identity">Email atau nama pengguna</label>
                        <input
                            id="identity"
                            className="input"
                            type="text"
                            autoComplete="username"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            autoFocus
                            value={form.identity}
                            onChange={(event) => setForm({ ...form, identity: event.target.value })}
                            placeholder="admin"
                        />
                    </div>

                    <div>
                        <label className="label" htmlFor="password">Password</label>
                        <input
                            id="password"
                            className="input"
                            type="password"
                            autoComplete="current-password"
                            value={form.password}
                            onChange={(event) => setForm({ ...form, password: event.target.value })}
                        />
                    </div>

                    {error && <p className="text-xs text-negative">{error}</p>}

                    {locked && (
                        <p className="text-xs text-muted">
                            Coba lagi dalam <span className="tabular-nums font-semibold">{lockedFor}</span> detik.
                        </p>
                    )}

                    <button
                        className="btn btn-primary w-full justify-center"
                        type="submit"
                        disabled={pending || locked}
                    >
                        <LogIn size={16} /> {pending ? 'Memproses...' : locked ? 'Tunggu sebentar' : 'Masuk'}
                    </button>
                </form>

                <p className="text-[11px] text-muted text-center mt-4">
                    Belum punya akun? Hubungi admin untuk mendapatkannya.
                </p>
            </div>
        </div>
    );
}
