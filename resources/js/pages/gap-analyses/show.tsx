import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    ClipboardCheck,
    LoaderCircle,
    Save,
} from 'lucide-react';
import { useState } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ScoreBadge } from '@/components/score-badge';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { GAP_RATINGS, ISO_SECTIONS, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import gapRoutes from '@/routes/gap-analyses';
import type { Action } from '@/types';

type Item = {
    id: number;
    rating: string | null;
    evidence: string | null;
    clause: { id: number; number: string; title: string };
};

type Scores = {
    sections: Record<string, number | null>;
    overall: number | null;
};

type Detail = {
    id: number;
    number: string;
    title: string;
    assessed_on: string;
    status: 'in-progress' | 'completed';
    summary: string | null;
    site: { id: number; code: string; name: string };
    creator: { id: number; name: string } | null;
    items: Item[];
    actions: Action[];
};

const sectionOf = (clause: string) => clause.split('.')[0];

export default function ShowGapAnalysis({
    analysis: g,
    scores,
    previous,
    users,
}: {
    analysis: Detail;
    scores: Scores;
    previous: (Scores & { number: string; assessed_on: string }) | null;
    users: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const editable = can('edit-audits') && g.status === 'in-progress';
    const [completing, setCompleting] = useState(false);
    const ratings = useForm({
        items: g.items.map((i) => ({
            id: i.id,
            rating: i.rating ?? '',
            evidence: i.evidence ?? '',
        })),
    });
    const summary = useForm({ summary: g.summary ?? '' });
    const rated = ratings.data.items.filter((i) => i.rating !== '').length;

    const setItem = (
        index: number,
        change: Partial<{ rating: string; evidence: string }>,
    ) =>
        ratings.setData(
            'items',
            ratings.data.items.map((item, i) =>
                i === index ? { ...item, ...change } : item,
            ),
        );

    const save = () =>
        ratings.put(gapRoutes.rate.url(g.id), { preserveScroll: true });

    return (
        <>
            <Head title={g.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${g.number} · ${g.title}`}
                    description={`${g.site.code} · ${g.site.name}`}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={gapRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {editable && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={save}
                                        disabled={ratings.processing}
                                    >
                                        {ratings.processing ? (
                                            <LoaderCircle className="animate-spin" />
                                        ) : (
                                            <Save />
                                        )}
                                        {t('Save ratings')}
                                    </Button>
                                    <Button
                                        onClick={() => {
                                            summary.clearErrors();
                                            setCompleting(true);
                                        }}
                                        disabled={ratings.isDirty}
                                        title={
                                            ratings.isDirty
                                                ? t('Save the ratings first.')
                                                : undefined
                                        }
                                    >
                                        <ClipboardCheck /> {t('Complete')}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {errors.status && (
                    <div
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <CircleAlert className="size-4 shrink-0" />
                        {errors.status}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6">
                        <section className="grid content-start gap-4 rounded-xl border p-5">
                            <div className="flex items-center justify-between">
                                <IdBadge>{g.number}</IdBadge>
                                <StatusBadge status={g.status} />
                            </div>
                            <dl className="grid gap-3 text-sm">
                                <div className="grid grid-cols-[7rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        {t('Assessed')}
                                    </dt>
                                    <dd className="font-medium">
                                        <DateCell value={g.assessed_on} />
                                    </dd>
                                </div>
                                <div className="grid grid-cols-[7rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        {t('By')}
                                    </dt>
                                    <dd className="font-medium">
                                        {g.creator?.name ?? '—'}
                                    </dd>
                                </div>
                                <div className="grid grid-cols-[7rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        {t('Rated')}
                                    </dt>
                                    <dd className="font-medium">
                                        {t(':n of :total clauses', {
                                            n: rated,
                                            total: g.items.length,
                                        })}
                                    </dd>
                                </div>
                            </dl>
                            {g.summary && (
                                <p className="text-sm whitespace-pre-line">
                                    {g.summary}
                                </p>
                            )}
                        </section>

                        <section className="grid gap-3 rounded-xl border p-5">
                            <div className="flex items-baseline justify-between gap-2">
                                <h2 className="font-medium">{t('Score')}</h2>
                                <span className="text-lg">
                                    <ScoreBadge score={scores.overall} />
                                </span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {previous
                                    ? t(
                                          'Conforms 100 %, partly 50 %, gap 0 %. Grey marks show :number (:date).',
                                          {
                                              number: previous.number,
                                              date: previous.assessed_on,
                                          },
                                      )
                                    : t(
                                          'Conforms 100 %, partly 50 %, gap 0 %; N/A is left out.',
                                      )}
                            </p>
                            <ul className="grid gap-2">
                                {Object.entries(ISO_SECTIONS).map(
                                    ([section, title]) => {
                                        const score =
                                            scores.sections[section] ?? null;
                                        const before =
                                            previous?.sections[section] ?? null;

                                        return (
                                            <li
                                                key={section}
                                                className="grid gap-1 text-sm"
                                            >
                                                <div className="flex justify-between gap-2">
                                                    <span className="text-muted-foreground">
                                                        §{section} {t(title)}
                                                    </span>
                                                    <ScoreBadge score={score} />
                                                </div>
                                                <span className="relative block h-2 rounded-full bg-muted">
                                                    {score !== null && (
                                                        <span
                                                            className="block h-full rounded-full bg-green-700 dark:bg-green-600"
                                                            style={{
                                                                width: `${score}%`,
                                                            }}
                                                        />
                                                    )}
                                                    {before !== null && (
                                                        <span
                                                            className="absolute -top-0.5 h-3 w-0.5 bg-gray-500"
                                                            style={{
                                                                left: `${before}%`,
                                                            }}
                                                            title={t(
                                                                ':number: :score%',
                                                                {
                                                                    number:
                                                                        previous?.number ??
                                                                        '',
                                                                    score: before,
                                                                },
                                                            )}
                                                        />
                                                    )}
                                                </span>
                                            </li>
                                        );
                                    },
                                )}
                            </ul>
                        </section>
                    </div>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        <section className="grid gap-4 rounded-xl border p-5">
                            <h2 className="font-medium">{t('Clauses')}</h2>
                            <InputError message={ratings.errors.items} />
                            <ol className="grid gap-1">
                                {g.items.map((item, index) => {
                                    const value = ratings.data.items[index];
                                    const section = sectionOf(
                                        item.clause.number,
                                    );
                                    const firstOfSection =
                                        index === 0 ||
                                        sectionOf(
                                            g.items[index - 1].clause.number,
                                        ) !== section;

                                    return (
                                        <li
                                            key={item.id}
                                            className="grid gap-2"
                                        >
                                            {firstOfSection && (
                                                <h3 className="mt-3 text-sm font-medium text-muted-foreground first:mt-0">
                                                    §{section}{' '}
                                                    {t(
                                                        ISO_SECTIONS[section] ??
                                                            '',
                                                    )}
                                                </h3>
                                            )}
                                            <div className="grid gap-2 rounded-md border p-3">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span
                                                        id={`clause-${item.id}`}
                                                        className="text-sm font-medium"
                                                    >
                                                        §{item.clause.number}{' '}
                                                        {item.clause.title}
                                                    </span>
                                                    {editable ? (
                                                        <ToggleGroup
                                                            type="single"
                                                            variant="outline"
                                                            size="sm"
                                                            aria-labelledby={`clause-${item.id}`}
                                                            value={value.rating}
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setItem(index, {
                                                                    rating: v,
                                                                })
                                                            }
                                                        >
                                                            {GAP_RATINGS.map(
                                                                (r) => (
                                                                    <ToggleGroupItem
                                                                        key={
                                                                            r.value
                                                                        }
                                                                        value={
                                                                            r.value
                                                                        }
                                                                        className="px-3"
                                                                    >
                                                                        {t(
                                                                            r.label,
                                                                        )}
                                                                    </ToggleGroupItem>
                                                                ),
                                                            )}
                                                        </ToggleGroup>
                                                    ) : item.rating ? (
                                                        <StatusBadge
                                                            status={item.rating}
                                                            label={t(
                                                                labelOf(
                                                                    GAP_RATINGS,
                                                                    item.rating,
                                                                ),
                                                            )}
                                                        />
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            {t('Not rated')}
                                                        </span>
                                                    )}
                                                </div>
                                                {editable ? (
                                                    <Textarea
                                                        rows={2}
                                                        aria-label={t(
                                                            'Evidence for §:clause',
                                                            {
                                                                clause: item
                                                                    .clause
                                                                    .number,
                                                            },
                                                        )}
                                                        placeholder={t(
                                                            value.rating ===
                                                                'gap' ||
                                                                value.rating ===
                                                                    'partial'
                                                                ? 'What is missing? (required)'
                                                                : 'Evidence seen (documents, records, interviews)',
                                                        )}
                                                        value={value.evidence}
                                                        onChange={(e) =>
                                                            setItem(index, {
                                                                evidence:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                ) : (
                                                    item.evidence && (
                                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                                            {item.evidence}
                                                        </p>
                                                    )
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                            {editable && (
                                <div className="flex justify-end">
                                    <Button
                                        onClick={save}
                                        disabled={ratings.processing}
                                    >
                                        {ratings.processing ? (
                                            <LoaderCircle className="animate-spin" />
                                        ) : (
                                            <Save />
                                        )}
                                        {t('Save ratings')}
                                    </Button>
                                </div>
                            )}
                        </section>

                        <ActionsPanel
                            actions={g.actions}
                            owners={users}
                            sourceNumber={g.number}
                            store={gapRoutes.actions.store(g.id)}
                            open
                            emptyText="Raise an action for each gap to close it."
                        />
                    </div>
                </div>
            </div>

            <FormDialog
                open={completing}
                onOpenChange={setCompleting}
                title="Complete gap analysis"
                description="Every clause needs a rating, and every gap or partial rating says what is missing."
                icon={ClipboardCheck}
                submitLabel="Complete"
                onSubmit={(e) => {
                    e.preventDefault();
                    summary.put(gapRoutes.complete.url(g.id), {
                        preserveScroll: true,
                        onSuccess: () => setCompleting(false),
                        // A blocked completion is shown above the clauses.
                        onError: (e) => e.status && setCompleting(false),
                    });
                }}
                processing={summary.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="gap-summary">
                        {t('Summary')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="gap-summary"
                        rows={5}
                        placeholder={t(
                            'Main gaps, readiness for certification, next steps.',
                        )}
                        value={summary.data.summary}
                        onChange={(e) =>
                            summary.setData('summary', e.target.value)
                        }
                    />
                    <InputError message={summary.errors.summary} />
                </div>
            </FormDialog>
        </>
    );
}

ShowGapAnalysis.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Gap analysis', href: gapRoutes.index() },
    ],
};
