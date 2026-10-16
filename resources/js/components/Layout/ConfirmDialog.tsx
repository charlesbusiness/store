import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';

interface ConfirmDialogProps {
    isOpen: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
    onCancel: () => void;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'primary';
}

export function ConfirmDialog({
    isOpen,
    title,
    message,
    onConfirm,
    onCancel,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'danger',
}: ConfirmDialogProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
        return () => setMounted(false);
    }, []);

    useEffect(() => {
        if (!mounted || !isOpen) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancel();
            if (e.key === 'Enter') onConfirm();
        };

        document.addEventListener('keydown', handleKeyDown);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [mounted, isOpen, onCancel, onConfirm]);

    if (!mounted || !isOpen) return null;

    const overlayStyle: React.CSSProperties = {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
        padding: '1rem',
    };

    const dialogStyle: React.CSSProperties = {
        backgroundColor: 'white',
        borderRadius: '0.5rem',
        boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
        maxWidth: '32rem',
        width: '100%',
        padding: '1.5rem',
    };

    const buttonStyle = (isPrimary: boolean): React.CSSProperties => ({
        padding: '0.5rem 1rem',
        borderRadius: '0.375rem',
        fontSize: '0.875rem',
        fontWeight: 500,
        cursor: 'pointer',
        border: 'none',
        backgroundColor: isPrimary
            ? variant === 'danger'
                ? '#dc2626'
                : '#2563eb'
            : '#e5e7eb',
        color: isPrimary ? 'white' : '#1f2937',
        marginLeft: '0.5rem',
    });

    const content = (
        <div style={overlayStyle} onClick={onCancel}>
            <div style={dialogStyle} onClick={(e) => e.stopPropagation()}>
                <h3 style={{ marginTop: 0, marginBottom: '1rem', fontSize: '1.125rem', fontWeight: 600 }}>
                    {title}
                </h3>
                <p style={{ marginBottom: '1.5rem', color: '#4b5563' }}>{message}</p>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <button style={buttonStyle(false)} onClick={onCancel}>
                        {cancelText}
                    </button>
                    <button style={buttonStyle(true)} onClick={onConfirm}>
                        {confirmText}
                    </button>
                </div>
            </div>
        </div>
    );

    return createPortal(content, document.body);
}