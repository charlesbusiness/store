interface PaginationProps {
    page: number;
    lastPage: number;
    onPageChange: (page: number) => void;
}

/**
 * Prev / page numbers / Next, mirroring the server-rendered recent table which
 * lists every page. Kept dumb: it only asks the caller to move to a page.
 */
export function Pagination({ page, lastPage, onPageChange }: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    const pages = Array.from({ length: lastPage }, (_, index) => index + 1);

    return (
        <div className='flex justify-center items-center gap-2 mt-6 flex-wrap'>
            <button
                type='button'
                onClick={() => onPageChange(page - 1)}
                disabled={page <= 1}
                className='btn alt sm'
            >
                Prev
            </button>

            {pages.map((number) => (
                <button
                    key={number}
                    type='button'
                    onClick={() => onPageChange(number)}
                    className={number === page ? 'btn sm' : 'btn alt sm'}
                    style={{ minWidth: 40, textAlign: 'center' }}
                    aria-current={number === page ? 'page' : undefined}
                >
                    {number}
                </button>
            ))}

            <button
                type='button'
                onClick={() => onPageChange(page + 1)}
                disabled={page >= lastPage}
                className='btn alt sm'
            >
                Next
            </button>
        </div>
    );
}
