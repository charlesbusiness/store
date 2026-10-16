export function groupByDate<T extends { transacted_on: string }>(items: T[]): Record<string, T[]> {
    return items.reduce((acc, item) => {
        const date = item.transacted_on.split('T')[0];
        (acc[date] ||= []).push(item);
        return acc;
    }, {} as Record<string, T[]>);
}

export function formatDateLabel(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
}