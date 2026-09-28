import { useEffect, useMemo, useRef, useState } from 'react';
import { Check, Search, X } from 'lucide-react';
import { useClickOutside } from '../hooks/useClickOutside';

export default function SearchSelect({
    options = [],
    value = '',
    onChange,
    placeholder = 'Cari...',
    emptyLabel = 'Tidak ada',
    allLabel = null,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const ref = useClickOutside(() => setOpen(false));
    const inputRef = useRef(null);

    const matches = useMemo(() => {
        const needle = term.trim().toLowerCase();
        return needle ? options.filter((option) => option.label.toLowerCase().includes(needle)) : options;
    }, [options, term]);

    const selected = options.find((option) => option.value === value) ?? null;
    const filtering = term.trim().length > 0 || selected !== null;

    useEffect(() => {
        setTerm(selected?.label ?? '');
    }, [value, options]);

    const pick = (next) => {
        onChange?.(next);
        setTerm(next === '' ? '' : (options.find((option) => option.value === next)?.label ?? ''));
        setOpen(false);
    };

    const optionClass = (isActive) =>
        `w-full flex items-center gap-2 px-3 py-2 text-sm text-left cursor-pointer transition-colors ${
            isActive ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
        }`;

    return (
        <div className={`relative ${className}`} ref={ref}>
            <Search size={15} className="text-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10" />
            <input
                ref={inputRef}
                value={term}
                onFocus={() => setOpen(true)}
                onChange={(e) => {
                    setTerm(e.target.value);
                    setOpen(true);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Escape') setOpen(false);
                    if (e.key === 'Enter' && matches.length) {
                        e.preventDefault();
                        pick(matches[0].value);
                    }
                }}
                placeholder={placeholder}
                className="input !pl-9 !pr-8"
            />
            {filtering && (
                <button
                    type="button"
                    onClick={() => pick('')}
                    title="Hapus pilihan"
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-faint hover:text-content cursor-pointer"
                >
                    <X size={14} />
                </button>
            )}

            {open && (
                <div className="absolute left-0 right-0 top-12 max-h-72 overflow-y-auto scrollbar-thin bg-surface rounded-xl shadow-xl z-40 py-1">
                    {allLabel !== null && (
                        <button type="button" onClick={() => pick('')} className={optionClass(!selected)}>
                            {allLabel}
                            {!selected && <Check size={14} className="ml-auto" />}
                        </button>
                    )}

                    {matches.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => pick(option.value)}
                            className={optionClass(selected?.value === option.value)}
                        >
                            <span className="truncate">{option.label}</span>
                            {selected?.value === option.value && <Check size={14} className="ml-auto" />}
                        </button>
                    ))}

                    {matches.length === 0 && <p className="text-xs text-muted text-center py-4">{emptyLabel}</p>}
                </div>
            )}
        </div>
    );
}
