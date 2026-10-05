import { CalendarDays, ChevronDown, ChevronUp, FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';

/** The small blue outline badge used for codes and IDs (EMP0001, JOB-1-00001…). */
export function IdBadge({ children }: { children: ReactNode }) {
    return (
        <span className="inline-block rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
            {children}
        </span>
    );
}

/** A date with a calendar icon that never wraps; renders "—" when empty. */
export function DateCell({
    value,
    children,
}: {
    value: string | null | undefined;
    /** Optional extra content after the date (e.g. an "Expired" note). */
    children?: ReactNode;
}) {
    const { date } = useFormat();

    if (!value) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex items-center gap-2 whitespace-nowrap">
            <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
            {date(value)}
            {children}
        </span>
    );
}

/** Description clamped to two lines, with the demo's "Show more" toggle for long text. */
export function ClampedText({
    text,
    limit = 100,
}: {
    text: string | null;
    limit?: number;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    if (!text) {
        return null;
    }

    return (
        <div className="text-muted-foreground">
            <p className={open ? undefined : 'line-clamp-2'}>{text}</p>
            {text.length > limit && (
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="mt-0.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                >
                    {open ? (
                        <ChevronUp className="size-3" />
                    ) : (
                        <ChevronDown className="size-3" />
                    )}
                    {t(open ? 'Show less' : 'Show more')}
                </button>
            )}
        </div>
    );
}

/** The demo's blue document icon linking to a record's attachment, or a dash when there is none. */
export function DocumentLink({
    href,
    fileName,
}: {
    href: string;
    fileName: string | null;
}) {
    const { t } = useTranslation();

    return fileName ? (
        <a
            href={href}
            className="inline-flex text-blue-600 hover:text-blue-700"
            aria-label={t('Download :name', { name: fileName })}
            title={fileName}
        >
            <FileText className="size-5" />
        </a>
    ) : (
        <span className="text-muted-foreground">—</span>
    );
}
