import { useSyncExternalStore } from 'react';

export const THEME_MODES = [
    { key: 'light', label: 'Terang', hint: 'Light' },
    { key: 'dark', label: 'Gelap', hint: 'Dark' },
    { key: 'system', label: 'Sistem', hint: 'Ikuti perangkat' },
];

export const THEME_ACCENTS = [
    { key: 'system', label: 'Sistem', swatch: '#2563eb' },
    { key: 'orange', label: 'Oranye', swatch: '#ea580c' },
    { key: 'blue', label: 'Biru', swatch: '#2563eb' },
    { key: 'emerald', label: 'Hijau', swatch: '#059669' },
    { key: 'purple', label: 'Ungu', swatch: '#9333ea' },
    { key: 'rose', label: 'Merah', swatch: '#e11d48' },
    { key: 'teal', label: 'Toska', swatch: '#0d9488' },
    { key: 'pink', label: 'Pink', swatch: '#db2777' },
    { key: 'slate', label: 'Abu', swatch: '#475569' },
];

export const DEFAULT_APPEARANCE = { mode: 'light', accent: 'system' };

const MODE_KEY = 'pos.theme_mode';
const ACCENT_KEY = 'pos.theme_accent';

const isValid = (value, list) => list.some((item) => item.key === value);

const readStorage = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (key, value) => {
    try {
        if (value) localStorage.setItem(key, value);
        else localStorage.removeItem(key);
    } catch {
        /* storage unavailable (private mode) — theme stays session-only */
    }
};

const prefersDark = () =>
    typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches;

/**
 * The appearance the browser itself asked for (no user/admin choice yet).
 */
export const systemAppearance = () => ({
    mode: prefersDark() ? 'dark' : 'light',
    accent: DEFAULT_APPEARANCE.accent,
});

const sanitize = (appearance) => ({
    mode: isValid(appearance?.mode, THEME_MODES) ? appearance.mode : DEFAULT_APPEARANCE.mode,
    accent: isValid(appearance?.accent, THEME_ACCENTS) ? appearance.accent : DEFAULT_APPEARANCE.accent,
});

/**
 * Appearance chosen by the user on this browser, if any.
 */
export function storedAppearance() {
    const mode = readStorage(MODE_KEY);
    const accent = readStorage(ACCENT_KEY);
    if (!mode && !accent) return null;
    return sanitize({ mode, accent });
}

function resolve(appearance) {
    return appearance.mode === 'system' || appearance.mode === undefined
        ? (prefersDark() ? 'dark' : 'light')
        : appearance.mode;
}

/**
 * Paint <html> with the resolved mode + accent. Called before first paint too.
 */
export function applyAppearance(appearance) {
    if (typeof document === 'undefined') return;
    const next = sanitize(appearance);
    const root = document.documentElement;
    root.dataset.theme = resolve(next);
    root.dataset.accent = next.accent;
    root.style.colorScheme = resolve(next);
}

let state = storedAppearance() ?? systemAppearance();
const listeners = new Set();

const emit = () => listeners.forEach((listener) => listener());

const subscribe = (listener) => {
    listeners.add(listener);
    return () => listeners.delete(listener);
};

const getSnapshot = () => state;

if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (state.mode === 'system') {
            applyAppearance(state);
            emit();
        }
    });
}

/**
 * Live appearance of the app, synced with the active DOM theme.
 */
export function useAppearance() {
    const appearance = useSyncExternalStore(subscribe, getSnapshot);
    return { ...appearance, resolvedMode: resolve(appearance) };
}

/**
 * Persist the appearance for this browser and paint it immediately.
 */
export function setAppearance(appearance) {
    const next = sanitize(appearance);
    state = next;
    writeStorage(MODE_KEY, next.mode);
    writeStorage(ACCENT_KEY, next.accent);
    applyAppearance(next);
    emit();
}

/**
 * Adopt the admin default without discarding a choice made on this browser.
 */
export function syncServerAppearance(appearance) {
    if (!appearance) return;
    const stored = storedAppearance();
    if (stored) {
        state = stored;
        applyAppearance(stored);
        emit();
        return;
    }
    state = sanitize(appearance);
    applyAppearance(state);
    emit();
}
