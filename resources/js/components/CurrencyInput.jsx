import { formatNumber } from '../api/client';

/**
 * Rupiah text input. Keeps raw digits in state and renders a formatted value
 * with thousand separators while typing.
 *
 * Simbol "Rp" digambar sebagai awalan di tepi kiri, bukan digabung ke teks
 * input, supaya nominalnya rata kanan dan kolom angkunya tetap lurus. Isi
 * `prefix` dengan string kosong untuk input yang tidak memakai awalan.
 *
 * `wrapperClassName` governs the outer wrapper that holds the awalan, sehingga
 * pemanggil bisa menata lebarunya di layout induk tanpa menimpa gaya input.
 */
export default function CurrencyInput({
    value = '',
    onChange,
    placeholder = '0',
    prefix = 'Rp',
    className = 'input',
    wrapperClassName = 'w-full',
    disabled = false,
}) {
    const display = value ? formatNumber(value) : '';

    const input = (
        <input
            inputMode="numeric"
            value={display}
            disabled={disabled}
            onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))}
            placeholder={placeholder}
            className={prefix ? `${className} pl-9` : className}
        />
    );

    if (!prefix) return input;

    return (
        <span className={`relative block ${wrapperClassName}`}>
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted pointer-events-none select-none">
                {prefix}
            </span>
            {input}
        </span>
    );
}
