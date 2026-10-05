import { Head, Link, useForm, useHttp } from '@inertiajs/react';
import {
    ArrowLeft,
    CornerDownRight,
    LoaderCircle,
    Send,
    Sparkles,
} from 'lucide-react';
import { toast } from 'sonner';
import { DateTimePicker, localDateTime } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PhotoPicker } from '@/components/photo-picker';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { INCIDENT_TYPES } from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import observationRoutes from '@/routes/observations';

type Site = {
    id: number;
    code: string;
    name: string;
    areas: { id: number; name: string }[];
};

type Escalated = {
    id: number;
    number: string;
    site_id: number;
    area_id: number | null;
    description: string;
    immediate_action: string | null;
    observed_at: string;
};

type Draft = {
    type: string;
    title: string;
    description: string;
    immediate_actions: string | null;
    area: string | null;
};

export default function ReportIncident({
    sites,
    defaultSiteId,
    observation,
    aiDrafting,
}: {
    sites: Site[];
    defaultSiteId: number | null;
    observation: Escalated | null;
    aiDrafting: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm({
        observation_id: observation ? String(observation.id) : '',
        type: '',
        title: '',
        site_id: String(
            observation?.site_id ??
                defaultSiteId ??
                (sites.length === 1 ? sites[0].id : ''),
        ),
        area_id: String(observation?.area_id ?? ''),
        occurred_at: localDateTime(observation?.observed_at),
        description: observation?.description ?? '',
        immediate_actions: observation?.immediate_action ?? '',
        photos: [] as File[],
    });
    const areas =
        sites.find((s) => String(s.id) === form.data.site_id)?.areas ?? [];
    const drafting = useHttp<
        { account: string; site_id: string },
        { draft: Draft }
    >('post', incidentRoutes.draft.url(), { account: '', site_id: '' });

    // Fill the form from the draft; the person checks every field before submitting.
    const draftReport = async () => {
        drafting.transform((data) => ({ ...data, site_id: form.data.site_id }));
        const { draft } = await drafting.post(incidentRoutes.draft.url());
        const area = areas.find((a) => a.name === draft.area);
        form.setData((data) => ({
            ...data,
            type: draft.type,
            title: draft.title,
            description: draft.description,
            immediate_actions: draft.immediate_actions ?? '',
            area_id: area ? String(area.id) : data.area_id,
        }));
        toast.success(
            t('Draft filled in. Check every field before reporting.'),
        );
    };

    const submit = () => {
        // Send the time as an instant, so the server (UTC) stores what the user meant.
        form.transform((data) => ({
            ...data,
            occurred_at: new Date(data.occurred_at).toISOString(),
        }));
        form.post(incidentRoutes.store.url(), { forceFormData: true });
    };

    return (
        <>
            <Head title={t('Report incident')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Report incident"
                    description="What happened, where and when. Add the people involved and investigate on the next page."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={incidentRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="grid max-w-3xl gap-6 rounded-xl border p-5"
                >
                    {observation && (
                        <div className="flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200">
                            <CornerDownRight className="size-4 shrink-0" />
                            <span>
                                {t('Escalated from')}{' '}
                                <Link
                                    href={observationRoutes.show(
                                        observation.id,
                                    )}
                                    className="font-medium underline"
                                >
                                    {observation.number}
                                </Link>
                                . {t('Its details are filled in below.')}
                            </span>
                        </div>
                    )}

                    {aiDrafting && !observation && (
                        <div className="grid gap-2 rounded-lg border border-dashed p-4">
                            <Label
                                htmlFor="inc-account"
                                className="flex items-center gap-2"
                            >
                                <Sparkles className="size-4 text-green-700" />
                                {t('Describe it in your own words (optional)')}
                            </Label>
                            <Textarea
                                id="inc-account"
                                rows={3}
                                placeholder={t(
                                    'What happened, where, what was done straight away…',
                                )}
                                value={drafting.data.account}
                                onChange={(e) =>
                                    drafting.setData('account', e.target.value)
                                }
                            />
                            <InputError message={drafting.errors.account} />
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'Only this text and the site’s area names are sent to Claude (Anthropic) to draft the form below. Leave out names; nothing is saved until you report.',
                                    )}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={drafting.processing}
                                    onClick={() =>
                                        void draftReport().catch(
                                            () => undefined,
                                        )
                                    }
                                >
                                    {drafting.processing ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Sparkles />
                                    )}
                                    {t('Draft the report')}
                                </Button>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="inc-type">
                                {t('Type')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="inc-type"
                                value={form.data.type}
                                onChange={(e) =>
                                    form.setData('type', e.target.value)
                                }
                            >
                                <option value="">{t('Select type')}</option>
                                {INCIDENT_TYPES.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {t(type.label)}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="inc-when">
                                {t('When')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <DateTimePicker
                                id="inc-when"
                                value={form.data.occurred_at}
                                onChange={(value) =>
                                    form.setData('occurred_at', value)
                                }
                            />
                            <InputError message={form.errors.occurred_at} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="inc-title">
                                {t('Title')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="inc-title"
                                placeholder={t(
                                    'e.g. Hand caught in press guard',
                                )}
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        {sites.length > 1 && !observation && (
                            <div className="grid gap-2">
                                <Label htmlFor="inc-site">
                                    {t('Site')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <SelectField
                                    id="inc-site"
                                    value={form.data.site_id}
                                    onChange={(e) =>
                                        form.setData((data) => ({
                                            ...data,
                                            site_id: e.target.value,
                                            area_id: '',
                                        }))
                                    }
                                >
                                    <option value="">{t('Select site')}</option>
                                    {sites.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.code} · {s.name}
                                        </option>
                                    ))}
                                </SelectField>
                                <InputError message={form.errors.site_id} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="inc-area">{t('Area')}</Label>
                            <SelectField
                                id="inc-area"
                                value={form.data.area_id}
                                onChange={(e) =>
                                    form.setData('area_id', e.target.value)
                                }
                                disabled={areas.length === 0}
                            >
                                <option value="">
                                    {t('Not sure / other')}
                                </option>
                                {areas.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.area_id} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="inc-description">
                            {t('What happened?')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="inc-description"
                            rows={5}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="inc-immediate">
                            {t('Immediate actions taken')}
                        </Label>
                        <Textarea
                            id="inc-immediate"
                            rows={3}
                            placeholder={t(
                                'e.g. first aid given, machine isolated, area barricaded',
                            )}
                            value={form.data.immediate_actions}
                            onChange={(e) =>
                                form.setData(
                                    'immediate_actions',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.immediate_actions} />
                    </div>

                    <PhotoPicker
                        photos={form.data.photos}
                        onChange={(photos) => form.setData('photos', photos)}
                        errors={form.errors}
                    />
                    <InputError message={form.errors.observation_id} />

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <Send />
                            )}
                            {t('Report incident')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ReportIncident.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentRoutes.index() },
        { title: 'Report', href: incidentRoutes.create() },
    ],
};
