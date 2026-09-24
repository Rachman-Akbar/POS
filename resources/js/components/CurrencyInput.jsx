import { formatNumber } from '../api/client';

/**
 * Rupiah text input. Keeps raw digits in state and renders a formatted value
 * with thousand separators while typing.
 */
export default function CurrencyInput({ value = '', onChange, placeholder = '0', className = 'input', disabled = false }) {
    const display = value ? `Rp ${formatNumber(value)}` : '';

    return (
        <input
            inputMode="numeric"
            value={display}
            disabled={disabled}
            onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))}
            placeholder={placeholder}
            className={className}
        />
    );
}