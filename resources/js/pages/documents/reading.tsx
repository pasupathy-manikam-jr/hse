import { Head, router } from '@inertiajs/react';
import { BookOpenCheck, Check, FileText } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { DOCUMENT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import documentRoutes from '@/routes/documents';

type Reading = {
    id: number;
    revision: string;
    change_summary: string | null;
    document: { number: string; title: string; type: string };
    has_file: boolean;
    acknowledged_at: string | null;
};

export default function MyReading({ revisions }: { revisions: Reading[] }) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();

    return (
        <>
            <Head title={t('My reading')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My reading"
                    description="Documents you have been asked to read. Open each one, then confirm you have read it."
                />
                {revisions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('Nothing to read right now.')}
                    </p>
                ) : (
                    <ul className="grid max-w-3xl gap-3">
                        {revisions.map((r) => (
                            <li
                                key={r.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                            >
                                <div className="grid gap-1">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <IdBadge>{r.document.number}</IdBadge>
                                        <span className="font-medium">
                                            {r.document.title}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {t('Rev :r', { r: r.revision })} ·{' '}
                                            {t(
                                                labelOf(
                                                    DOCUMENT_TYPES,
                                                    r.document.type,
                                                ),
                                            )}
                                        </span>
                                    </span>
                                    {r.change_summary && (
                                        <span className="text-sm text-muted-foreground">
                                            {t('What changed: :s', {
                                                s: r.change_summary,
                                            })}
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {r.has_file && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={documentRoutes.revisions.file.url(
                                                    r.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText /> {t('Open')}
                                            </a>
                                        </Button>
                                    )}
                                    {r.acknowledged_at ? (
                                        <StatusBadge
                                            status="verified"
                                            label={t('Read :t', {
                                                t: dateTime(r.acknowledged_at),
                                            })}
                                        />
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.put(
                                                    documentRoutes.revisions.acknowledge.url(
                                                        r.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Check /> {t('I have read it')}
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <BookOpenCheck className="size-4" />
                    {t('Your confirmation is recorded with the date and time.')}
                </p>
            </div>
        </>
    );
}

MyReading.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My reading', href: documentRoutes.reading() },
    ],
};
