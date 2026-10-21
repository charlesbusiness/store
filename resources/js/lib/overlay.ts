let depth = 0;
let previousOverflow = '';

/**
 * Locks page scrolling while an overlay is open. Nested overlays (a record
 * modal with a confirmation dialog on top) each push once and the page is
 * only unlocked again when the last one closes.
 */
export function pushOverlay(): void {
    if (depth === 0) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }
    depth += 1;
}

export function popOverlay(): void {
    if (depth === 0) return;
    depth -= 1;
    if (depth === 0) {
        document.body.style.overflow = previousOverflow;
        previousOverflow = '';
    }
}
