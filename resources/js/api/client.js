import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

export const formatIDR = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0);

export const formatPct = (value) => `${Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}%`;