import { useCallback, useEffect, useState } from 'react';
import { BackspaceIcon } from '@heroicons/react/24/outline';
import { useCalculatorHistory } from '@/hooks';
import type { CalculationHistoryEntry } from '@/hooks/useCalculatorHistory';

type Operator = '+' | '-' | '*' | '/';

const OPERATOR_LABELS: Record<Operator, string> = {
    '+': '+',
    '-': '−',
    '*': '×',
    '/': '÷',
};

const MAX_INPUT_DIGITS = 12;

function calculate(a: number, b: number, operator: Operator): number {
    switch (operator) {
        case '+':
            return a + b;
        case '-':
            return a - b;
        case '*':
            return a * b;
        case '/':
            return b === 0 ? Number.NaN : a / b;
        default:
            return b;
    }
}

function formatNumber(value: number): string {
    if (!Number.isFinite(value)) return 'Error';
    return String(Number.parseFloat(value.toPrecision(12)));
}

function digitCount(value: string): number {
    return value.replace(/[-.]/g, '').length;
}

export function Calculator() {
    const [display, setDisplay] = useState('0');
    const [stored, setStored] = useState<number | null>(null);
    const [operator, setOperator] = useState<Operator | null>(null);
    const [overwrite, setOverwrite] = useState(true);
    const [expression, setExpression] = useState('');
    const [hasError, setHasError] = useState(false);
    const [historyExpanded, setHistoryExpanded] = useState(false);
    const { history, addEntry, clearHistory } = useCalculatorHistory();

    const clear = useCallback(() => {
        setDisplay('0');
        setStored(null);
        setOperator(null);
        setOverwrite(true);
        setExpression('');
        setHasError(false);
    }, []);

    const pressDigit = useCallback(
        (digit: string) => {
            if (hasError) {
                clear();
                setDisplay(digit);
                setOverwrite(false);
                return;
            }

            if (overwrite) {
                setDisplay(digit);
                setOverwrite(false);
                return;
            }

            if (digitCount(display) >= MAX_INPUT_DIGITS) return;
            setDisplay(display === '0' ? digit : display + digit);
        },
        [clear, display, hasError, overwrite]
    );

    const pressDecimal = useCallback(() => {
        if (hasError) {
            clear();
            setDisplay('0.');
            setOverwrite(false);
            return;
        }

        if (overwrite) {
            setDisplay('0.');
            setOverwrite(false);
            return;
        }

        if (!display.includes('.')) {
            setDisplay(`${display}.`);
        }
    }, [clear, display, hasError, overwrite]);

    const pressOperator = useCallback(
        (nextOperator: Operator) => {
            if (hasError) return;

            const current = Number.parseFloat(display);

            if (operator !== null && stored !== null && !overwrite) {
                const result = calculate(stored, current, operator);
                if (!Number.isFinite(result)) {
                    setDisplay('Error');
                    setExpression('');
                    setStored(null);
                    setOperator(null);
                    setOverwrite(true);
                    setHasError(true);
                    return;
                }

                const formatted = formatNumber(result);
                setStored(result);
                setDisplay(formatted);
                setOperator(nextOperator);
                setExpression(`${formatted} ${OPERATOR_LABELS[nextOperator]}`);
                setOverwrite(true);
                return;
            }

            const base = overwrite && operator !== null && stored !== null ? stored : current;
            setStored(base);
            setOperator(nextOperator);
            setExpression(`${formatNumber(base)} ${OPERATOR_LABELS[nextOperator]}`);
            setOverwrite(true);
        },
        [display, hasError, operator, overwrite, stored]
    );

    const pressEquals = useCallback(() => {
        if (hasError) return;

        if (operator === null || stored === null) {
            setExpression(`${display} =`);
            setOverwrite(true);
            return;
        }

        const current = overwrite ? stored : Number.parseFloat(display);
        const result = calculate(stored, current, operator);

        if (!Number.isFinite(result)) {
            setDisplay('Error');
            setExpression('');
            setStored(null);
            setOperator(null);
            setOverwrite(true);
            setHasError(true);
            return;
        }

        const expressionText = `${formatNumber(stored)} ${OPERATOR_LABELS[operator]} ${formatNumber(current)}`;
        const formattedResult = formatNumber(result);

        setExpression(`${expressionText} =`);
        setDisplay(formattedResult);
        setStored(null);
        setOperator(null);
        setOverwrite(true);
        addEntry({ expression: expressionText, result: formattedResult });
    }, [addEntry, display, hasError, operator, overwrite, stored]);

    const toggleSign = useCallback(() => {
        if (hasError || display === '0') return;
        setDisplay(display.startsWith('-') ? display.slice(1) : `-${display}`);
    }, [display, hasError]);

    const pressPercent = useCallback(() => {
        if (hasError) return;

        const current = Number.parseFloat(display);
        if (!Number.isFinite(current)) return;

        let result = current / 100;
        if (operator !== null && stored !== null && (operator === '+' || operator === '-')) {
            result = stored * (current / 100);
        }

        setDisplay(formatNumber(result));
        setOverwrite(false);
    }, [display, hasError, operator, stored]);

    const pressBackspace = useCallback(() => {
        if (hasError) {
            clear();
            return;
        }

        if (overwrite) {
            setDisplay('0');
            return;
        }

        const next = display.slice(0, -1);
        setDisplay(next === '' || next === '-' ? '0' : next);
    }, [clear, display, hasError, overwrite]);

    const loadHistoryEntry = useCallback((entry: CalculationHistoryEntry) => {
        setDisplay(entry.result);
        setStored(null);
        setOperator(null);
        setOverwrite(true);
        setExpression(`${entry.expression} =`);
        setHasError(false);
    }, []);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;
            if (event.metaKey || event.ctrlKey || event.altKey) return;

            const { key } = event;

            if (key === 'Escape') return;
            if (target?.tagName === 'BUTTON' && (key === 'Enter' || key === ' ')) return;

            if (/^\d$/.test(key)) {
                event.preventDefault();
                pressDigit(key);
                return;
            }

            if (key === '.' || key === ',') {
                event.preventDefault();
                pressDecimal();
                return;
            }

            if (key === '+' || key === '-' || key === '*' || key === 'x' || key === 'X' || key === '/') {
                event.preventDefault();
                pressOperator(key === 'x' || key === 'X' ? '*' : (key as Operator));
                return;
            }

            if (key === 'Enter' || key === '=') {
                event.preventDefault();
                pressEquals();
                return;
            }

            if (key === 'Backspace') {
                event.preventDefault();
                pressBackspace();
                return;
            }

            if (key === 'c' || key === 'C') {
                event.preventDefault();
                clear();
                return;
            }

            if (key === '%') {
                event.preventDefault();
                pressPercent();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [clear, pressBackspace, pressDecimal, pressDigit, pressEquals, pressOperator, pressPercent]);

    return (
        <div className='mx-auto w-full max-w-xs space-y-3 pb-2'>
            <div className='rounded-xl border border-gray-200 bg-gray-50 p-3'>
                <div className='flex items-center justify-between gap-2'>
                    <span className='min-h-[1rem] truncate text-xs text-gray-500'>
                        {expression || '\u00a0'}
                    </span>
                    <button
                        type='button'
                        onClick={pressBackspace}
                        className='btn alt sm inline-flex items-center'
                        title='Backspace'
                        aria-label='Backspace'
                    >
                        <BackspaceIcon className='h-4 w-4' />
                    </button>
                </div>
                <div
                    className={`overflow-x-auto whitespace-nowrap text-right text-3xl font-semibold tabular-nums ${
                        hasError ? 'text-red-600' : ''
                    }`}
                    aria-live='polite'
                >
                    {display}
                </div>
            </div>

            <div className='grid grid-cols-4 gap-2'>
                <button type='button' onClick={clear} className='btn alt' aria-label='Clear'>
                    AC
                </button>
                <button type='button' onClick={toggleSign} className='btn alt' aria-label='Toggle sign'>
                    ±
                </button>
                <button type='button' onClick={pressPercent} className='btn alt' aria-label='Percent'>
                    %
                </button>
                <button type='button' onClick={() => pressOperator('/')} className='btn' aria-label='Divide'>
                    ÷
                </button>

                {['7', '8', '9'].map((digit) => (
                    <button
                        key={digit}
                        type='button'
                        onClick={() => pressDigit(digit)}
                        className='btn alt'
                    >
                        {digit}
                    </button>
                ))}
                <button type='button' onClick={() => pressOperator('*')} className='btn' aria-label='Multiply'>
                    ×
                </button>

                {['4', '5', '6'].map((digit) => (
                    <button
                        key={digit}
                        type='button'
                        onClick={() => pressDigit(digit)}
                        className='btn alt'
                    >
                        {digit}
                    </button>
                ))}
                <button type='button' onClick={() => pressOperator('-')} className='btn' aria-label='Subtract'>
                    −
                </button>

                {['1', '2', '3'].map((digit) => (
                    <button
                        key={digit}
                        type='button'
                        onClick={() => pressDigit(digit)}
                        className='btn alt'
                    >
                        {digit}
                    </button>
                ))}
                <button type='button' onClick={() => pressOperator('+')} className='btn' aria-label='Add'>
                    +
                </button>

                <button
                    type='button'
                    onClick={() => pressDigit('0')}
                    className='btn alt col-span-2'
                >
                    0
                </button>
                <button type='button' onClick={pressDecimal} className='btn alt' aria-label='Decimal point'>
                    .
                </button>
                <button type='button' onClick={pressEquals} className='btn' aria-label='Equals'>
                    =
                </button>
            </div>

             <div className='space-y-2 border-t border-gray-200 pt-3'>
                 <div className='flex items-center justify-between gap-2'>
                     <span className='text-sm font-semibold'>History</span>
                     <button
                         type='button'
                         onClick={() => setHistoryExpanded((expanded) => !expanded)}
                         className='btn alt sm'
                         aria-expanded={historyExpanded}
                         aria-controls='calculator-history'
                     >
                         {historyExpanded ? 'Hide history' : 'View history'}
                     </button>
                 </div>

                 {historyExpanded && (
                     <div id='calculator-history'>
                         {history.length === 0 ? (
                             <p className='text-xs text-gray-500'>No calculations yet.</p>
                         ) : (
                             <>
                                 <div className='flex justify-end'>
                                     <button
                                         type='button'
                                         onClick={clearHistory}
                                         className='btn alt sm'
                                         aria-label='Clear calculation history'
                                     >
                                         Clear history
                                     </button>
                                 </div>
                                 <ul className='max-h-40 space-y-1 overflow-y-auto'>
                                     {history.map((entry) => (
                                         <li key={entry.id}>
                                             <button
                                                 type='button'
                                                 onClick={() => loadHistoryEntry(entry)}
                                                 className='flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 px-2 py-1 text-left text-sm hover:bg-gray-50'
                                                 title='Use this result'
                                             >
                                                 <span className='truncate text-gray-500'>{entry.expression}</span>
                                                 <span className='shrink-0 font-semibold tabular-nums'>{entry.result}</span>
                                             </button>
                                         </li>
                                     ))}
                                 </ul>
                             </>
                         )}
                     </div>
                 )}
             </div>
        </div>
    );
}
