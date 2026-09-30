import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { ArrowLeft, Lock } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { landingFor } from '../auth/permissions';

/**
 * Halaman internal yang wajib login. `anyPermission` hanya untuk menyembunyikan
 * halaman bila user tidak punya satu pun hak akses; backend tetap memverifikasi
 * tiap request, jadi ini bukan lapisan keamanan.
 */
export default function ProtectedRoute({ children, anyPermission = [] }) {
    const { isAuthenticated, checking, canAny } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();

    if (checking) {
        return (
            <div className="min-h-screen flex items-center justify-center text-muted">
                Memuat sesi...
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    }

    if (anyPermission.length > 0 && !canAny(...anyPermission)) {
        return <Forbidden />;
    }

    return children;
}

/**
 * Halaman 403. Menyodorkan user kembali ke halaman yang memang ia punya aksesnya
 * supaya tidak tersangkut di layar mati setelah salah buka URL atau permission-nya
 * dicabut admin.
 */
function Forbidden() {
    const { user } = useAuth();
    const navigate = useNavigate();

    const home = landingFor(user?.permissions);

    return (
        <div className="min-h-screen flex items-center justify-center px-4 py-10">
            <div className="w-full max-w-md text-center">
                <div className="w-14 h-14 rounded-2xl bg-surface-2 text-muted flex items-center justify-center mx-auto">
                    <Lock size={26} />
                </div>

                <h1 className="text-xl font-bold mt-4">Akses ditolak</h1>
                <p className="text-sm text-muted mt-2">
                    Akun {user?.username ? <span className="font-semibold text-content">{user.username}</span> : 'Anda'}{' '}
                    tidak punya hak akses ke halaman ini. Kalau ini tidak sesuai, minta admin menyesuaikan
                    permission-nya.
                </p>

                {home && (
                    <button
                        onClick={() => navigate(home, { replace: true })}
                        className="btn btn-primary justify-center mx-auto mt-5"
                    >
                        <ArrowLeft size={15} /> Kembali ke halaman utama
                    </button>
                )}
            </div>
        </div>
    );
}
