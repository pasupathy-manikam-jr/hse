import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    CircleCheck,
    CircleX,
    Save,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ScoreBadge } from '@/components/score-badge';
import { PhotoGallery, PhotoPicker } from '@/components/photo-picker';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { RATING_PASS, acceptable } from '@/lib/hse';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import inspectionRoutes from '@/routes/inspections';
import photoRoutes from '@/routes/photos';
import type { Action, Inspection, InspectionAnswer } from '@/types';

type Detail = Inspection & { answers: InspectionAnswer[]; actions: Action[] };
type Entry = { answer: string; notes: string; photos: File[] };

const YES_NO = [
    { value: 'yes', label: 'Yes', on: 'data-[state=on]:bg-emerald-600' },
    { value: 'no', label: 'No', on: 'data-[state=on]:bg-red-600' },
    { value: 'na', label: 'N/A', on: 'data-[state=on]:bg-gray-500' },
] as const;

/** Same rule as InspectionAnswer::evaluate(): pass, fail, or neither (null). */
function evaluate(a: InspectionAnswer, value: string): boolean | null {
    if (value === '') {
        return null;
    }

    if (a.response_type === 'yes-no-na') {
        return value === 'yes' ? true : value === 'no' ? false : null;
    }

    if (a.response_type === 'rating') {
        return Number(value) >= (a.min === null ? RATING_PASS : Number(a.min));
    }

    if (a.response_type === 'number') {
        const n = Number(value);

        return (
            !Number.isNaN(n) &&
            (a.min === null || n >= Number(a.min)) &&
            (a.max === null || n <= Number(a.max))
        );
    }

    return null;
}

function Result({ passed }: { passed: boolean | null }) {
    const { t } = useTranslation();

    if (passed === null) {
        return <StatusBadge status="draft" label={t('Not scored')} />;
    }

    return passed ? (
        <StatusBadge status="passed" />
    ) : (
        <StatusBadge status="failed" />
    );
}

export default function ShowInspection({
    inspection: i,
    users,
}: {
    inspection: Detail;
    users: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const { errors, auth } = usePage().props as {
        errors: Record<string, string>;
        auth: { user: { id: number } };
    };
    const filling = i.status === 'in-progress' && i.created_by === auth.user.id;
    const [deleting, setDeleting] = useState(false);
    const form = useForm({
        notes: i.notes ?? '',
        answers: Object.fromEntries(
            i.answers.map((a) => [
                a.id,
                { answer: a.answer ?? '', notes: a.notes ?? '', photos: [] },
            ]),
        ) as Record<string, Entry>,
        complete: false,
    });

    const entry = (id: number) => form.data.answers[id];
    const setEntry = (id: number, changes: Partial<Entry>) =>
        form.setData('answers', {
            ...form.data.answers,
            [id]: { ...form.data.answers[id], ...changes },
        });
    const answered = i.answers.filter(
        (a) => a.response_type === 'text' || entry(a.id).answer !== '',
    ).length;

    const save = (complete: boolean) => {
        form.transform((data) => ({ ...data, complete }));
        form.post(inspectionRoutes.answers.url(i.id), {
            forceFormData: true,
            preserveScroll: true,
            // Saved photos now show in the gallery; clear the pickers.
            onSuccess: () =>
                form.setData(
                    'answers',
                    Object.fromEntries(
                        Object.entries(form.data.answers).map(([id, e]) => [
                            id,
                            { ...e, photos: [] },
                        ]),
                    ),
                ),
        });
    };

    return (
        <>
            <Head title={i.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={i.number}
                    description={`${i.template_name} · ${i.site.code}${i.area ? ` · ${i.area.name}` : ''}`}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={inspectionRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {i.status === 'in-progress' &&
                                can('delete-inspections') && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setDeleting(true)}
                                    >
                                        <Trash2 /> {t('Discard')}
                                    </Button>
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
                    <section className="grid content-start gap-4 rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <StatusBadge status={i.status} />
                            <span className="text-3xl">
                                <ScoreBadge score={i.score} />
                            </span>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            {(
                                [
                                    ['Checklist', i.template_name],
                                    [
                                        'Where',
                                        `${i.site.code}${i.area ? ` · ${i.area.name}` : ''}`,
                                    ],
                                    ['Inspector', i.creator?.name ?? '—'],
                                    ['Started', dateTime(i.created_at)],
                                    [
                                        'Completed',
                                        i.completed_at
                                            ? dateTime(i.completed_at)
                                            : '—',
                                    ],
                                ] as const
                            ).map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[6rem_1fr] gap-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                        </dl>
                        {filling && (
                            <p className="text-sm text-muted-foreground">
                                {t(':n of :total answered', {
                                    n: answered,
                                    total: i.answers.length,
                                })}
                            </p>
                        )}
                        {i.status === 'in-progress' && !filling && (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'In progress. Only the inspector who started it can fill it in.',
                                )}
                            </p>
                        )}
                    </section>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        <ol className="grid gap-3">
                            {i.answers.map((a, n) => {
                                const value = filling
                                    ? entry(a.id).answer
                                    : (a.answer ?? '');
                                const passed = filling
                                    ? evaluate(a, value)
                                    : a.passed;

                                return (
                                    <li
                                        key={a.id}
                                        className={cn(
                                            'grid gap-3 rounded-xl border p-4',
                                            passed === false &&
                                                'border-red-300 dark:border-red-900',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="grid gap-1">
                                                <span className="font-medium">
                                                    {n + 1}. {a.question}
                                                </span>
                                                <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                    {a.critical && (
                                                        <StatusBadge
                                                            status="high"
                                                            label={t(
                                                                'Critical',
                                                            )}
                                                        />
                                                    )}
                                                    {acceptable(a) &&
                                                        t('Acceptable: :r', {
                                                            r: acceptable(a),
                                                        })}
                                                </span>
                                            </div>
                                            {value !== '' &&
                                                a.response_type !== 'text' &&
                                                (passed === true ? (
                                                    <CircleCheck className="size-5 shrink-0 text-emerald-600" />
                                                ) : passed === false ? (
                                                    <CircleX className="size-5 shrink-0 text-red-600" />
                                                ) : null)}
                                        </div>

                                        {filling ? (
                                            <>
                                                {a.response_type ===
                                                    'yes-no-na' && (
                                                    <ToggleGroup
                                                        type="single"
                                                        variant="outline"
                                                        aria-label={a.question}
                                                        value={value}
                                                        onValueChange={(v) =>
                                                            v &&
                                                            setEntry(a.id, {
                                                                answer: v,
                                                            })
                                                        }
                                                        className="w-full sm:w-80"
                                                    >
                                                        {YES_NO.map((o) => (
                                                            <ToggleGroupItem
                                                                key={o.value}
                                                                value={o.value}
                                                                className={cn(
                                                                    'flex-1 data-[state=on]:text-white',
                                                                    o.on,
                                                                )}
                                                            >
                                                                {t(o.label)}
                                                            </ToggleGroupItem>
                                                        ))}
                                                    </ToggleGroup>
                                                )}
                                                {a.response_type ===
                                                    'rating' && (
                                                    <ToggleGroup
                                                        type="single"
                                                        variant="outline"
                                                        aria-label={a.question}
                                                        value={value}
                                                        onValueChange={(v) =>
                                                            v &&
                                                            setEntry(a.id, {
                                                                answer: v,
                                                            })
                                                        }
                                                        className="w-full sm:w-80"
                                                    >
                                                        {[
                                                            '1',
                                                            '2',
                                                            '3',
                                                            '4',
                                                            '5',
                                                        ].map((r) => (
                                                            <ToggleGroupItem
                                                                key={r}
                                                                value={r}
                                                                className="flex-1 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                                                            >
                                                                {r}
                                                            </ToggleGroupItem>
                                                        ))}
                                                    </ToggleGroup>
                                                )}
                                                {a.response_type ===
                                                    'number' && (
                                                    <Input
                                                        aria-label={a.question}
                                                        inputMode="decimal"
                                                        className="sm:w-40"
                                                        value={value}
                                                        onChange={(e) =>
                                                            setEntry(a.id, {
                                                                answer: e.target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                )}
                                                {a.response_type === 'text' && (
                                                    <Textarea
                                                        aria-label={a.question}
                                                        rows={2}
                                                        value={value}
                                                        onChange={(e) =>
                                                            setEntry(a.id, {
                                                                answer: e.target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                )}
                                                <InputError
                                                    message={
                                                        (
                                                            form.errors as Record<
                                                                string,
                                                                string
                                                            >
                                                        )[
                                                            `answers.${a.id}.answer`
                                                        ]
                                                    }
                                                />
                                                {passed === false && (
                                                    <div className="grid gap-3 rounded-lg bg-red-50 p-3 dark:bg-red-950/40">
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor={`notes-${a.id}`}
                                                            >
                                                                {t(
                                                                    "What's wrong?",
                                                                )}
                                                            </Label>
                                                            <Textarea
                                                                id={`notes-${a.id}`}
                                                                rows={2}
                                                                value={
                                                                    entry(a.id)
                                                                        .notes
                                                                }
                                                                onChange={(e) =>
                                                                    setEntry(
                                                                        a.id,
                                                                        {
                                                                            notes: e
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <PhotoPicker
                                                            photos={
                                                                entry(a.id)
                                                                    .photos
                                                            }
                                                            onChange={(
                                                                photos,
                                                            ) =>
                                                                setEntry(a.id, {
                                                                    photos,
                                                                })
                                                            }
                                                            errors={Object.fromEntries(
                                                                Object.entries(
                                                                    form.errors,
                                                                )
                                                                    .filter(
                                                                        ([k]) =>
                                                                            k.startsWith(
                                                                                `answers.${a.id}.photos`,
                                                                            ),
                                                                    )
                                                                    .map(
                                                                        ([
                                                                            ,
                                                                            v,
                                                                        ]) => [
                                                                            'photos',
                                                                            v,
                                                                        ],
                                                                    ),
                                                            )}
                                                        />
                                                    </div>
                                                )}
                                            </>
                                        ) : (
                                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                                {a.response_type !== 'text' && (
                                                    <Result passed={passed} />
                                                )}
                                                <span className="font-medium">
                                                    {a.answer === 'na'
                                                        ? t('N/A')
                                                        : a.answer
                                                          ? t(
                                                                a.answer
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                    a.answer.slice(
                                                                        1,
                                                                    ),
                                                            )
                                                          : '—'}
                                                </span>
                                                {a.notes && (
                                                    <span className="text-muted-foreground">
                                                        · {a.notes}
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                        {a.photos.length > 0 && (
                                            <PhotoGallery
                                                photos={a.photos}
                                                url={(id) =>
                                                    photoRoutes.show.url(id)
                                                }
                                            />
                                        )}
                                    </li>
                                );
                            })}
                        </ol>

                        {filling ? (
                            <section className="grid gap-3 rounded-xl border p-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="ins-notes">
                                        {t('General notes')}
                                    </Label>
                                    <Textarea
                                        id="ins-notes"
                                        rows={2}
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => save(false)}
                                    >
                                        <Save /> {t('Save progress')}
                                    </Button>
                                    <Button
                                        disabled={form.processing}
                                        onClick={() => save(true)}
                                        className="bg-green-700 text-white hover:bg-green-800"
                                    >
                                        <Send /> {t('Complete inspection')}
                                    </Button>
                                </div>
                            </section>
                        ) : (
                            <>
                                {i.notes && (
                                    <section className="rounded-xl border p-4">
                                        <h2 className="text-sm text-muted-foreground">
                                            {t('General notes')}
                                        </h2>
                                        <p className="mt-1 whitespace-pre-line">
                                            {i.notes}
                                        </p>
                                    </section>
                                )}
                                {i.status === 'completed' && (
                                    <ActionsPanel
                                        actions={i.actions}
                                        owners={users}
                                        sourceNumber={i.number}
                                        store={inspectionRoutes.actions.store(
                                            i.id,
                                        )}
                                        open
                                        emptyText="Nothing failed, so no actions were raised."
                                    />
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title="Discard inspection"
                description="This unfinished inspection and its answers will be deleted."
                confirmLabel="Discard"
                onConfirm={() => router.delete(inspectionRoutes.destroy(i.id))}
            />
        </>
    );
}

ShowInspection.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inspections', href: inspectionRoutes.index() },
    ],
};
