import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Ban,
    CircleAlert,
    FilePen,
    Lock,
    LockOpen,
    Pause,
    Play,
    Save,
    SquarePen,
    Trash2,
    TriangleAlert,
    Wind,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { localDateTime } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PermitFields, permitPayload } from '@/components/permit-fields';
import type { PermitFormData, PermitOptions } from '@/components/permit-fields';
import { SignDialog } from '@/components/sign-dialog';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { PERMIT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import permitRoutes from '@/routes/permits';
import riskRoutes from '@/routes/risk-assessments';
import type { Permit } from '@/types';

type Person = { id: number; name: string };

type Detail = Permit & {
    contractor: {
        id: number;
        name: string;
        approved: boolean;
        insurance_expires_on: string | null;
    } | null;
    risk_assessment: {
        id: number;
        number: string;
        revision: number;
        title: string;
        status: string;
    };
    workers: Person[];
    approver: Person | null;
    gas_tests: {
        id: number;
        oxygen: string;
        lel: string;
        h2s: string;
        co: string;
        passed: boolean;
        tested_at: string;
        tester: Person | null;
    }[];
    isolations: {
        id: number;
        point: string;
        method: string;
        lock_no: string | null;
        isolated_at: string;
        isolator: Person | null;
        removed_at: string | null;
        remover: Person | null;
    }[];
    signatures: {
        id: number;
        meaning: string;
        signer_name: string;
        signed_at: string;
    }[];
};

type Conflict = {
    id: number;
    number: string;
    type: string;
    status: string;
    valid_from: string;
    valid_to: string;
};

type Step = {
    status: string;
    title: string;
    description: string;
    signed?: boolean;
    reason?: boolean;
    submitLabel: string;
};

const STEPS: Record<string, Step> = {
    approve: {
        status: 'approved',
        title: 'Approve permit',
        description:
            'You confirm the precautions are in place and the crew is competent. The work can start within the permit window.',
        signed: true,
        submitLabel: 'Approve and sign',
    },
    close: {
        status: 'closed',
        title: 'Close permit',
        description:
            'You confirm the work is finished, the area is safe and every isolation is removed.',
        signed: true,
        submitLabel: 'Close and sign',
    },
    suspend: {
        status: 'suspended',
        title: 'Suspend work',
        description:
            'Work stops until the permit is resumed. Say why (alarm, weather, gas reading...).',
        signed: false,
        reason: true,
        submitLabel: 'Suspend',
    },
    cancel: {
        status: 'cancelled',
        title: 'Cancel permit',
        description: 'The permit will not be used. Say why.',
        signed: false,
        reason: true,
        submitLabel: 'Cancel permit',
    },
};

const GAS_FIELDS = [
    ['oxygen', 'O₂ (%)'],
    ['lel', 'LEL (%)'],
    ['h2s', 'H₂S (ppm)'],
    ['co', 'CO (ppm)'],
] as const;

export default function ShowPermit({
    permit: p,
    precautions,
    problems,
    conflicts,
    gasLimits,
    ...options
}: {
    permit: Detail;
    precautions: Record<string, string>;
    problems: string[];
    conflicts: Conflict[];
    gasLimits: {
        oxygen: [number, number];
        lel: number;
        h2s: number;
        co: number;
    };
} & Partial<PermitOptions>) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const { errors, auth } = usePage().props as {
        errors: Record<string, string>;
        auth: { user: { id: number } };
    };
    const open = !['closed', 'cancelled'].includes(p.status);
    const requested = p.status === 'requested';
    const canWork = can('create-permits');

    const [step, setStep] = useState<Step | null>(null);
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const checked = useForm({
        checked: Object.keys(p.precautions ?? {}).filter(
            (k) => p.precautions?.[k],
        ),
    });
    const gas = useForm({ oxygen: '', lel: '', h2s: '', co: '' });
    const isolation = useForm({ point: '', method: '', lock_no: '' });
    const details = useForm<PermitFormData>({
        type: p.type,
        site_id: String(p.site_id),
        area_id: String(p.area_id ?? ''),
        risk_assessment_id: String(p.risk_assessment_id),
        contractor_id: String(p.contractor_id ?? ''),
        description: p.description,
        valid_from: localDateTime(p.valid_from),
        valid_to: localDateTime(p.valid_to),
        worker_ids: p.workers.map((w) => w.id),
    });

    const move = (status: string) =>
        router.put(
            permitRoutes.transition.url(p.id),
            { status },
            { preserveScroll: true },
        );

    const facts: [string, ReactNode][] = [
        ['Type', t(labelOf(PERMIT_TYPES, p.type))],
        ['Where', `${p.site.code}${p.area ? ` · ${p.area.name}` : ''}`],
        ['From', dateTime(p.valid_from)],
        ['To', dateTime(p.valid_to)],
        [
            'Risk assessment',
            <Link
                key="ra"
                href={riskRoutes.show(p.risk_assessment.id)}
                className="text-blue-600 hover:underline"
            >
                {p.risk_assessment.number} rev {p.risk_assessment.revision}
            </Link>,
        ],
        [
            'Contractor',
            p.contractor ? (
                <span key="c" className="flex flex-wrap items-center gap-2">
                    {p.contractor.name}
                    {!p.contractor.approved && (
                        <StatusBadge
                            status="pending"
                            label={t('Not approved')}
                        />
                    )}
                </span>
            ) : (
                t('Own staff')
            ),
        ],
        ['Requested by', p.creator?.name ?? '—'],
    ];

    if (p.approved_at) {
        facts.push([
            'Approved',
            `${dateTime(p.approved_at)}${p.approver ? ` · ${p.approver.name}` : ''}`,
        ]);
    }

    if (p.closed_at) {
        facts.push(['Closed', dateTime(p.closed_at)]);
    }

    return (
        <>
            <Head title={p.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${p.number} · ${t(labelOf(PERMIT_TYPES, p.type))}`}
                    description={p.description}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={permitRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {requested && canWork && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        details.clearErrors();
                                        setEditing(true);
                                    }}
                                >
                                    <SquarePen /> {t('Edit')}
                                </Button>
                            )}
                            {requested &&
                                can('approve-permits') &&
                                p.created_by !== auth.user.id && (
                                    <Button
                                        onClick={() => setStep(STEPS.approve)}
                                    >
                                        <BadgeCheck /> {t('Approve')}
                                    </Button>
                                )}
                            {canWork &&
                                (p.status === 'approved' ||
                                    p.status === 'suspended') && (
                                    <Button onClick={() => move('active')}>
                                        <Play />{' '}
                                        {p.status === 'approved'
                                            ? t('Start work')
                                            : t('Resume')}
                                    </Button>
                                )}
                            {canWork && p.status === 'active' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setStep(STEPS.suspend)}
                                >
                                    <Pause /> {t('Suspend')}
                                </Button>
                            )}
                            {canWork &&
                                (p.status === 'active' ||
                                    p.status === 'suspended') && (
                                    <Button
                                        onClick={() => setStep(STEPS.close)}
                                    >
                                        <Lock /> {t('Close')}
                                    </Button>
                                )}
                            {canWork &&
                                (requested || p.status === 'approved') && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setStep(STEPS.cancel)}
                                    >
                                        <Ban /> {t('Cancel')}
                                    </Button>
                                )}
                            {requested && can('delete-permits') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 /> {t('Delete')}
                                </Button>
                            )}
                        </div>
                    }
                />

                {errors.status && (
                    <div
                        role="alert"
                        className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <CircleAlert className="mt-0.5 size-4 shrink-0" />
                        {errors.status}
                    </div>
                )}

                {conflicts.length > 0 && (
                    <div
                        role="alert"
                        className="grid gap-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <span className="flex items-center gap-2 font-medium">
                            <TriangleAlert className="size-4" />
                            {t('Conflicting work in the same area')}
                        </span>
                        {conflicts.map((c) => (
                            <Link
                                key={c.id}
                                href={permitRoutes.show(c.id)}
                                className="underline"
                            >
                                {c.number} · {t(labelOf(PERMIT_TYPES, c.type))}{' '}
                                ({c.status}), {dateTime(c.valid_from)} –{' '}
                                {dateTime(c.valid_to)}
                            </Link>
                        ))}
                    </div>
                )}

                {problems.length > 0 && (
                    <div className="grid gap-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        <span className="font-medium">
                            {t('Before this permit can be approved:')}
                        </span>
                        <ul className="list-disc ps-5">
                            {problems.map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {p.status_reason &&
                    ['suspended', 'cancelled'].includes(p.status) && (
                        <div className="rounded-lg border bg-muted/50 px-4 py-3 text-sm">
                            <span className="font-medium">
                                {p.status === 'suspended'
                                    ? t('Suspended:')
                                    : t('Cancelled:')}
                            </span>{' '}
                            {p.status_reason}
                        </div>
                    )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6">
                        <section className="grid content-start gap-4 rounded-xl border p-5">
                            <div className="flex items-center justify-between">
                                <IdBadge>{p.number}</IdBadge>
                                <StatusBadge status={p.status} />
                            </div>
                            <dl className="grid gap-3 text-sm">
                                {facts.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="grid grid-cols-[7rem_1fr] gap-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {t(label)}
                                        </dt>
                                        <dd className="font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="grid gap-2 rounded-xl border p-5">
                            <h2 className="font-medium">{t('Workers')}</h2>
                            <ul className="grid gap-1 text-sm">
                                {p.workers.map((w) => (
                                    <li key={w.id}>{w.name}</li>
                                ))}
                            </ul>
                        </section>

                        {p.signatures.length > 0 && (
                            <section className="grid gap-2 rounded-xl border p-5">
                                <h2 className="font-medium">
                                    {t('Signatures')}
                                </h2>
                                <ul className="grid gap-1 text-sm">
                                    {p.signatures.map((s) => (
                                        <li key={s.id}>
                                            <span className="font-medium capitalize">
                                                {s.meaning}
                                            </span>{' '}
                                            · {s.signer_name} ·{' '}
                                            <span className="text-muted-foreground">
                                                {dateTime(s.signed_at)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        <section className="grid gap-3 rounded-xl border p-5">
                            <h2 className="font-medium">{t('Precautions')}</h2>
                            <form
                                noValidate
                                className="grid gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    checked.put(
                                        permitRoutes.precautions.url(p.id),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <ul className="grid gap-2">
                                    {Object.entries(precautions).map(
                                        ([key, label]) => (
                                            <li key={key}>
                                                <Label className="flex items-start gap-3 font-normal">
                                                    <Checkbox
                                                        className="mt-0.5"
                                                        disabled={
                                                            !requested ||
                                                            !canWork
                                                        }
                                                        checked={checked.data.checked.includes(
                                                            key,
                                                        )}
                                                        onCheckedChange={(on) =>
                                                            checked.setData(
                                                                'checked',
                                                                on === true
                                                                    ? [
                                                                          ...checked
                                                                              .data
                                                                              .checked,
                                                                          key,
                                                                      ]
                                                                    : checked.data.checked.filter(
                                                                          (k) =>
                                                                              k !==
                                                                              key,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    {t(label)}
                                                </Label>
                                            </li>
                                        ),
                                    )}
                                </ul>
                                {requested && canWork && (
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={checked.processing}
                                        >
                                            <Save /> {t('Save precautions')}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </section>

                        {p.type === 'confined-space' && (
                            <section className="grid gap-3 rounded-xl border p-5">
                                <div>
                                    <h2 className="font-medium">
                                        {t('Gas tests')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Pass: O₂ :min–:max %, LEL below :lel %, H₂S below :h2s ppm, CO below :co ppm.',
                                            {
                                                min: gasLimits.oxygen[0],
                                                max: gasLimits.oxygen[1],
                                                lel: gasLimits.lel,
                                                h2s: gasLimits.h2s,
                                                co: gasLimits.co,
                                            },
                                        )}
                                    </p>
                                </div>
                                {p.gas_tests.length > 0 && (
                                    <ul className="divide-y text-sm">
                                        {p.gas_tests.map((g) => (
                                            <li
                                                key={g.id}
                                                className="flex flex-wrap items-center gap-x-4 gap-y-1 py-2"
                                            >
                                                <StatusBadge
                                                    status={
                                                        g.passed
                                                            ? 'passed'
                                                            : 'failed'
                                                    }
                                                />
                                                <span className="tabular-nums">
                                                    O₂ {Number(g.oxygen)} · LEL{' '}
                                                    {Number(g.lel)} · H₂S{' '}
                                                    {Number(g.h2s)} · CO{' '}
                                                    {Number(g.co)}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {dateTime(g.tested_at)}
                                                    {g.tester &&
                                                        ` · ${g.tester.name}`}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {open && canWork && (
                                    <form
                                        noValidate
                                        className="grid gap-3 sm:grid-cols-[repeat(4,1fr)_auto] sm:items-end"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            gas.post(
                                                permitRoutes.gasTests.store.url(
                                                    p.id,
                                                ),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        gas.reset(),
                                                },
                                            );
                                        }}
                                    >
                                        {GAS_FIELDS.map(([field, label]) => (
                                            <div
                                                key={field}
                                                className="grid gap-1"
                                            >
                                                <Label htmlFor={`gas-${field}`}>
                                                    {label}
                                                </Label>
                                                <Input
                                                    id={`gas-${field}`}
                                                    inputMode="decimal"
                                                    value={gas.data[field]}
                                                    onChange={(e) =>
                                                        gas.setData(
                                                            field,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={gas.errors[field]}
                                                />
                                            </div>
                                        ))}
                                        <Button
                                            type="submit"
                                            disabled={gas.processing}
                                        >
                                            <Wind /> {t('Record')}
                                        </Button>
                                    </form>
                                )}
                            </section>
                        )}

                        <section className="grid gap-3 rounded-xl border p-5">
                            <div>
                                <h2 className="font-medium">
                                    {t('Isolations (lockout / tagout)')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Every isolation must be removed before the permit closes.',
                                    )}
                                </p>
                            </div>
                            {p.isolations.length > 0 && (
                                <ul className="divide-y text-sm">
                                    {p.isolations.map((iso) => (
                                        <li
                                            key={iso.id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                                        >
                                            <div className="grid gap-0.5">
                                                <span className="flex items-center gap-2 font-medium">
                                                    {iso.removed_at ? (
                                                        <LockOpen className="size-4 text-muted-foreground" />
                                                    ) : (
                                                        <Lock className="size-4 text-red-600" />
                                                    )}
                                                    {iso.point}
                                                    {iso.lock_no &&
                                                        ` · ${t('lock :n', { n: iso.lock_no })}`}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {iso.method} ·{' '}
                                                    {t('isolated :t by :name', {
                                                        t: dateTime(
                                                            iso.isolated_at,
                                                        ),
                                                        name:
                                                            iso.isolator
                                                                ?.name ?? '—',
                                                    })}
                                                    {iso.removed_at &&
                                                        ` · ${t(
                                                            'removed :t by :name',
                                                            {
                                                                t: dateTime(
                                                                    iso.removed_at,
                                                                ),
                                                                name:
                                                                    iso.remover
                                                                        ?.name ??
                                                                    '—',
                                                            },
                                                        )}`}
                                                </span>
                                            </div>
                                            {!iso.removed_at &&
                                                open &&
                                                canWork && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.put(
                                                                permitRoutes.isolations.remove.url(
                                                                    [
                                                                        p.id,
                                                                        iso.id,
                                                                    ],
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <LockOpen />{' '}
                                                        {t('Remove')}
                                                    </Button>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {open && canWork && (
                                <form
                                    noValidate
                                    className="grid gap-3 sm:grid-cols-[2fr_2fr_1fr_auto] sm:items-end"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        isolation.post(
                                            permitRoutes.isolations.store.url(
                                                p.id,
                                            ),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    isolation.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <div className="grid gap-1">
                                        <Label htmlFor="iso-point">
                                            {t('Isolation point')}
                                        </Label>
                                        <Input
                                            id="iso-point"
                                            placeholder={t(
                                                'e.g. MCC-2 breaker 14',
                                            )}
                                            value={isolation.data.point}
                                            onChange={(e) =>
                                                isolation.setData(
                                                    'point',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={isolation.errors.point}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="iso-method">
                                            {t('Method')}
                                        </Label>
                                        <Input
                                            id="iso-method"
                                            placeholder={t(
                                                'e.g. Breaker off, locked, tagged',
                                            )}
                                            value={isolation.data.method}
                                            onChange={(e) =>
                                                isolation.setData(
                                                    'method',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={isolation.errors.method}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="iso-lock">
                                            {t('Lock no.')}
                                        </Label>
                                        <Input
                                            id="iso-lock"
                                            value={isolation.data.lock_no}
                                            onChange={(e) =>
                                                isolation.setData(
                                                    'lock_no',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={isolation.processing}
                                    >
                                        <Lock /> {t('Isolate')}
                                    </Button>
                                </form>
                            )}
                        </section>
                    </div>
                </div>
            </div>

            {step && (
                <SignDialog
                    open
                    onOpenChange={(next) => !next && setStep(null)}
                    title={step.title}
                    description={step.description}
                    url={permitRoutes.transition.url(p.id)}
                    data={{ status: step.status }}
                    signed={step.signed}
                    reason={step.reason}
                    submitLabel={step.submitLabel}
                />
            )}

            {requested && options.sites && (
                <FormDialog
                    open={editing}
                    onOpenChange={setEditing}
                    title="Edit permit"
                    description="A permit can be changed until it is approved."
                    icon={FilePen}
                    onSubmit={(e) => {
                        e.preventDefault();
                        details.transform(permitPayload);
                        details.put(permitRoutes.update.url(p.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        });
                    }}
                    processing={details.processing}
                >
                    <PermitFields
                        form={details}
                        options={options as PermitOptions}
                        siteLocked
                    />
                </FormDialog>
            )}

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title="Delete permit"
                description="This permit request will be permanently deleted."
                onConfirm={() => router.delete(permitRoutes.destroy(p.id))}
            />
        </>
    );
}

ShowPermit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Permits to work', href: permitRoutes.index() },
    ],
};
