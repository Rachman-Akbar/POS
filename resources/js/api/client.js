import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

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