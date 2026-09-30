import axios from 'axios';

const TOKEN_KEY = 'pos.token';

export const tokenStore = {
    get: () => localStorage.getItem(TOKEN_KEY),
    set: (token) => localStorage.setItem(TOKEN_KEY, token),
    clear: () => localStorage.removeItem(TOKEN_KEY),
};

export const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

/**
 * Callback dipasang AuthProvider supaya 401 bisa memaksa client kembali ke
 * halaman login tanpa mengimpor router di lapisan API.
 */
let onUnauthenticated = null;

export const setUnauthenticatedHandler = (handler) => {
    onUnauthenticated = handler;
};

api.interceptors.request.use((config) => {
    const token = tokenStore.get();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            tokenStore.clear();

            if (onUnauthenticated) {
                onUnauthenticated();
            }
        }

        return Promise.reject(error);
    },
);

/**
 * Pesan error yang layak ditampilkan ke user dari respons API.
 *
 * Urutannya penting: `errors` (validasi per field) diperiksa lebih dulu dari
 * `message`. Untuk respons 422 Laravel selalu mengirim `message` generik
 * seperti "The given data was invalid.", yang tidak memberi tahu kasir apa yang
 * sebenarnya salah. `message` baru dipakai kalau tidak ada error per field,
 * misalnya exception 403/404 dari backend.
 */
export const errorMessage = (error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') => {
    const data = error?.response?.data;

    const firstField = Object.keys(data?.errors ?? {})[0];

    if (firstField) {
        return data.errors[firstField][0];
    }

    if (data?.message) {
        return data.message;
    }

    return fallback;
};

/**
 * Sisa detik mengunci dari header `Retry-After`, atau 0 kalau respons tidak
 * membawanya. Dipakai form login untuk menghitung mundur dan mematikan tombol.
 */
export const retryAfterSeconds = (error) => {
    const header = error?.response?.headers?.['retry-after'];

    const seconds = Number(header);

    return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : 0;
};

export const rupiahParts = (value) => ({
    symbol: 'Rp.',
    amount: new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(Number(value ?? 0) || 0),
});

export const formatIDR = (value) => {
    const { symbol, amount } = rupiahParts(value);
    return `${symbol} ${amount}`;
};

export const formatPct = (value) => `${Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}%`;

export const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);

export const parseNumber = (raw) => {
    const digits = String(raw ?? '').replace(/\D/g, '');
    return digits ? Number(digits) : 0;
};