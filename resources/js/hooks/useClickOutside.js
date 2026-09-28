import { useEffect, useRef } from 'react';

/**
 * Run a callback when a click lands outside the returned ref.
 */
export function useClickOutside(onClose) {
    const ref = useRef(null);

    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [onClose]);

    return ref;
}
