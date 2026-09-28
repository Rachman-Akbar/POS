import axios from "axios";

const configuredBaseUrl = String(import.meta.env.VITE_API_BASE_URL ?? "")
    .trim()
    .replace(/\/+$/, "");

const configuredOrigin = configuredBaseUrl.endsWith("/api")
    ? configuredBaseUrl.slice(0, -4)
    : configuredBaseUrl;
const apiBaseUrl = configuredBaseUrl
    ? configuredBaseUrl.endsWith("/api") ? configuredBaseUrl : `${configuredBaseUrl}/api`
    : "/api";
const sanctumBaseUrl = configuredOrigin || "";

export const api = axios.create({
    baseURL: apiBaseUrl,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
    withCredentials: true,
    withXSRFToken: true,
    timeout: 30000,
});

export const csrf = () => axios.get(`${sanctumBaseUrl}/sanctum/csrf-cookie`, {
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});

function responsePayload(response) {
    return response?.data ?? response ?? null;
}

export function resourceData(response) {
    const payload = responsePayload(response);

    if (payload && typeof payload === "object" && !Array.isArray(payload) && "data" in payload) {
        return payload.data;
    }

    return payload;
}

export function collectionData(response) {
    const payload = responsePayload(response);

    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (Array.isArray(payload?.data?.data)) {
        return payload.data.data;
    }

    return [];
}

export function paginatedData(response) {
    const payload = responsePayload(response);

    if (Array.isArray(payload)) {
        return {
            data: payload,
            links: null,
            meta: null,
        };
    }

    if (Array.isArray(payload?.data)) {
        return {
            ...payload,
            data: payload.data,
            links: payload.links ?? null,
            meta: payload.meta ?? null,
        };
    }

    if (payload?.data && Array.isArray(payload.data.data)) {
        return {
            ...payload.data,
            data: payload.data.data,
            links: payload.data.links ?? null,
            meta: payload.data.meta ?? null,
        };
    }

    return {
        data: [],
        links: null,
        meta: null,
    };
}

export function errorMessage(error, fallback = "Terjadi kesalahan.") {
    if (!axios.isAxiosError(error)) {
        return fallback;
    }

    if (error.code === "ECONNABORTED") {
        return "Permintaan ke backend terlalu lama. Pastikan Laravel sedang berjalan.";
    }

    if (!error.response) {
        return "Backend tidak dapat dihubungi. Pastikan Laravel aktif di http://127.0.0.1:8000.";
    }

    const errors = error.response.data?.errors;

    return (errors ? Object.values(errors).flat()[0] : undefined)
        ?? error.response.data?.message
        ?? fallback;
}
