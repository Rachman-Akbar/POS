import { rupiahParts } from '../api/client';

export default function Price({ value, className = '', amountClassName = '' }) {
    const { symbol, amount } = rupiahParts(value);

    return (
        <span className={`inline-flex items-baseline justify-end gap-1.5 whitespace-nowrap tabular-nums ${className}`}>
            <span className="shrink-0">{symbol}</span>
            <span className={amountClassName}>{amount}</span>
        </span>
    );
}

export function PriceRow({ value, className = '', symbolClassName = 'text-muted', amountClassName = '' }) {
    const { symbol, amount } = rupiahParts(value);

    return (
        <span className={`inline-grid grid-cols-[1.75rem_auto] items-baseline tabular-nums ${className}`}>
            <span className={`text-right ${symbolClassName}`}>{symbol}</span>
            <span className={`text-right ${amountClassName}`}>{Number(value) > 0 ? amount : '-'}</span>
        </span>
    );
}
