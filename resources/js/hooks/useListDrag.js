import { useCallback, useRef, useState } from 'react';

const keyUnderPointer = (event) => {
    const element = document.elementFromPoint(event.clientX, event.clientY);
    return element?.closest('[data-drag-key]')?.dataset.dragKey ?? null;
};

/**
 * Reorder a list by dragging a row with the pointer, works with mouse and touch.
 * The dragged row must expose a `data-drag-key` attribute holding its key.
 *
 * @param  {(fromKey: string, toKey: string) => void} onReorder
 */
export default function useListDrag(onReorder) {
    const [dragging, setDragging] = useState(null);
    const [overKey, setOverKey] = useState(null);
    const overRef = useRef(null);
    const reorderRef = useRef(onReorder);
    reorderRef.current = onReorder;

    const startDrag = useCallback((event, key) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        event.preventDefault();
        overRef.current = key;
        setOverKey(key);
        setDragging(key);

        const handleMove = (moveEvent) => {
            const next = keyUnderPointer(moveEvent);
            overRef.current = next;
            setOverKey(next);
        };

        const handleEnd = () => {
            window.removeEventListener('pointermove', handleMove);
            window.removeEventListener('pointerup', handleEnd);
            window.removeEventListener('pointercancel', handleEnd);

            if (overRef.current && overRef.current !== key) {
                reorderRef.current(key, overRef.current);
            }

            overRef.current = null;
            setDragging(null);
            setOverKey(null);
        };

        window.addEventListener('pointermove', handleMove);
        window.addEventListener('pointerup', handleEnd);
        window.addEventListener('pointercancel', handleEnd);
    }, []);

    return { dragging, overKey, startDrag };
}
