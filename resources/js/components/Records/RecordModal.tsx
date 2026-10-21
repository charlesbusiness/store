import { ReactNode, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { popOverlay, pushOverlay } from '@/lib/overlay';

interface RecordModalProps {
    isOpen: boolean;
    title: string;
    onClose: () => void;
    children: ReactNode;
}

/**
 * Full screen overlay used by the record (sale/purchase) forms. Locks page
 * scrolling, traps focus inside the panel and closes on Escape or a backdrop
 * click.
 */
export function RecordModal({ isOpen, title, onClose, children }: RecordModalProps) {
    const panelRef = useRef<HTMLDivElement | null>(null);
    const onCloseRef = useRef(onClose);

    useEffect(() => {
        onCloseRef.current = onClose;
    }, [onClose]);

    useEffect(() => {
        if (!isOpen) return;

        const panel = panelRef.current;
        const previouslyFocused = document.activeElement as HTMLElement | null;

        pushOverlay();
        panel?.focus();

        const handleKeyDown = (event: KeyboardEvent) => {
            // A nested widget (e.g. the item search dropdown) handles Escape itself.
            if (event.defaultPrevented) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                onCloseRef.current();
                return;
            }

            if (event.key !== 'Tab' || !panel) return;

            const focusable = panel.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && (active === first || active === panel)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            popOverlay();
            previouslyFocused?.focus?.();
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const content = (
        <div
            className="record-modal"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onCloseRef.current();
            }}
        >
            <div
                className="record-modal-panel"
                role="dialog"
                aria-modal="true"
                aria-label={title}
                tabIndex={-1}
                ref={panelRef}
            >
                <div className="record-modal-head">
                    <h2>{title}</h2>
                    <button
                        type="button"
                        className="record-modal-close"
                        onClick={() => onCloseRef.current()}
                        title="Close"
                        aria-label="Close"
                    >
                        &times;
                    </button>
                </div>
                <div className="record-modal-body">{children}</div>
            </div>
        </div>
    );

    return createPortal(content, document.body);
}
