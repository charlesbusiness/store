import { useState, useRef, useEffect } from 'react';

interface Option {
    value: string;
    label: string;
    /** Optional right-aligned note, e.g. how much stock is left. */
    hint?: string;
    [key: string]: any;
}

interface SearchableSelectProps {
    options: Option[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    onCreate?: (label: string) => void;
    searchFn?: (query: string) => Promise<Option[]>;
    debounceMs?: number;
    className?: string;
    disabled?: boolean;
    required?: boolean;
    name?: string;
}

export function SearchableSelect({
    options: initialOptions,
    value,
    onChange,
    placeholder = 'Select...',
    onCreate,
    searchFn,
    debounceMs = 300,
    className = '',
    disabled = false,
    required = false,
    name,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [filteredOptions, setFilteredOptions] = useState<Option[]>(initialOptions);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [isLoading, setIsLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        if (searchFn && searchQuery) {
            setIsLoading(true);
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(async () => {
                try {
                    const results = await searchFn(searchQuery);
                    setFilteredOptions(results);
                } catch (error) {
                    console.error('Search failed:', error);
                    setFilteredOptions([]);
                } finally {
                    setIsLoading(false);
                }
            }, debounceMs);
        } else {
            setFilteredOptions(
                initialOptions.filter((opt) =>
                    opt.label.toLowerCase().includes(searchQuery.toLowerCase())
                )
            );
        }
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [searchQuery, searchFn, initialOptions, debounceMs]);

    useEffect(() => {
        setFilteredOptions(
            initialOptions.filter((opt) =>
                opt.label.toLowerCase().includes(searchQuery.toLowerCase())
            )
        );
        setHighlightedIndex(-1);
    }, [initialOptions]);

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (!isOpen) {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setIsOpen(true);
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setHighlightedIndex((prev) =>
                    prev < filteredOptions.length - 1 ? prev + 1 : prev
                );
                break;
            case 'ArrowUp':
                e.preventDefault();
                setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : prev));
                break;
            case 'Enter':
                e.preventDefault();
                if (highlightedIndex >= 0 && filteredOptions[highlightedIndex]) {
                    onChange(filteredOptions[highlightedIndex].value);
                    setIsOpen(false);
                    setSearchQuery('');
                } else if (onCreate && searchQuery.trim()) {
                    onCreate(searchQuery.trim());
                }
                break;
            case 'Escape':
                e.preventDefault();
                setIsOpen(false);
                setSearchQuery('');
                inputRef.current?.blur();
                break;
            case 'Tab':
                setIsOpen(false);
                setSearchQuery('');
                break;
        }
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const query = e.target.value;
        setSearchQuery(query);
        if (!isOpen) setIsOpen(true);
    };

    const handleOptionClick = (option: Option) => {
        onChange(option.value);
        setIsOpen(false);
        setSearchQuery('');
    };

    const handleBlur = (e: React.FocusEvent) => {
        if (!dropdownRef.current?.contains(e.relatedTarget as Node)) {
            setTimeout(() => setIsOpen(false), 150);
        }
    };

    const displayValue = initialOptions.find((opt) => opt.value === value)?.label ?? value;
    const inputValue = isOpen ? searchQuery : displayValue;

    return (
        <div className={`relative ${className}`}>
            <input
                ref={inputRef}
                type="text"
                value={inputValue}
                onChange={handleInputChange}
                onKeyDown={handleKeyDown}
                onFocus={() => {
                    setIsOpen(true);
                    setSearchQuery('');
                }}
                onBlur={handleBlur}
                placeholder={placeholder}
                disabled={disabled}
                required={required}
                name={name}
                className={`input w-full pr-10 ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                autoComplete="off"
                aria-autocomplete="list"
                aria-expanded={isOpen}
                aria-controls="dropdown"
            />
            <div
                className="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-[var(--mut)]"
                aria-hidden="true"
            >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            {isOpen && !disabled && (
                <div
                    ref={dropdownRef}
                    id="dropdown"
                    className="absolute z-50 w-full mt-1 bg-[var(--card)] text-[var(--ink)] border border-[var(--line)] rounded-md shadow-lg max-h-60 overflow-auto"
                >
                    {isLoading && (
                        <div className="p-3 text-center text-[var(--mut)] text-sm">
                            Searching...
                        </div>
                    )}
                    {filteredOptions.length === 0 && !isLoading && (
                        <div className="p-3 text-center text-[var(--mut)] text-sm">
                            {onCreate && searchQuery
                                ? `Press Enter to create "${searchQuery}"`
                                : 'No options found'}
                        </div>
                    )}
                    {filteredOptions.map((option, index) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => handleOptionClick(option)}
                            onMouseEnter={() => setHighlightedIndex(index)}
                            className={`w-full flex items-center justify-between gap-3 text-left px-3 py-2 hover:bg-[var(--bg)] ${
                                index === highlightedIndex ? 'bg-[var(--bg)]' : ''
                            }`}
                        >
                            <span>{option.label}</span>
                            {option.hint ? (
                                <span className="text-xs text-[var(--mut)] whitespace-nowrap">{option.hint}</span>
                            ) : null}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}