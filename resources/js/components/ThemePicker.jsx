import { useState } from 'react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { THEME_ACCENTS, THEME_MODES, setAppearance, useAppearance } from '../theme';

const MODE_ICONS = { light: Sun, dark: Moon, system: Monitor };
const MODE_TONES = {
    light: 'bg-amber-100 text-amber-600 dark:bg-amber-400/20 dark:text-amber-300',
    dark: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-400/20 dark:text-indigo-300',
    system: 'bg-surface-3 text-muted',
};

export default function ThemePicker() {
    const [open, setOpen] = useState(false);
    const appearance = useAppearance();
    const ActiveModeIcon = MODE_ICONS[appearance.resolvedMode] ?? Sun;

    return (
        <div className="relative shrink-0">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                title="Tampilan & warna aplikasi"
                aria-label="Tampilan & warna aplikasi"
                aria-expanded={open}
                className={`btn !px-2.5 !py-2 ${MODE_TONES[appearance.resolvedMode] ?? MODE_TONES.system}`}
            >
                <ActiveModeIcon size={16} />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 top-12 w-64 bg-surface rounded-xl p-3 shadow-xl z-40">
                        <div className="text-[11px] font-semibold text-muted uppercase tracking-wide mb-2">Mode Tampilan</div>
                        <div className="grid grid-cols-3 gap-1.5">
                            {THEME_MODES.map((mode) => {
                                const Icon = MODE_ICONS[mode.key];
                                const active = appearance.mode === mode.key;
                                return (
                                    <button
                                        key={mode.key}
                                        type="button"
                                        onClick={() => setAppearance({ ...appearance, mode: mode.key })}
                                        title={mode.hint}
                                        className={`flex flex-col items-center gap-1 px-2 py-2 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer ${
                                            active ? 'bg-accent-soft text-accent-ink' : 'bg-surface-2 text-muted hover:bg-surface-3'
                                        }`}
                                    >
                                        <Icon size={15} />
                                        {mode.label}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="text-[11px] font-semibold text-muted uppercase tracking-wide mt-4 mb-2">Warna Aksen</div>
                        <div className="flex flex-wrap gap-2">
                            {THEME_ACCENTS.map((accent) => {
                                const active = appearance.accent === accent.key;
                                return (
                                    <button
                                        key={accent.key}
                                        type="button"
                                        onClick={() => setAppearance({ ...appearance, accent: accent.key })}
                                        title={accent.label}
                                        aria-label={accent.label}
                                        className={`w-8 h-8 rounded-full flex items-center justify-center transition-transform ${
                                            active ? 'ring-2 ring-accent ring-offset-2 ring-offset-surface' : 'hover:scale-105'
                                        }`}
                                        style={{ backgroundColor: accent.swatch }}
                                    >
                                        {active && <Check size={14} className="text-white" strokeWidth={3} />}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
