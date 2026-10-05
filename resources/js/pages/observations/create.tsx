import { Head, useForm } from '@inertiajs/react';
import { CloudOff, LoaderCircle, MapPin, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DateTimePicker, localDateTime } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { PhotoPicker } from '@/components/photo-picker';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { OBSERVATION_TYPES, POTENTIALS } from '@/lib/hse';
import { enqueue, flush, queued } from '@/lib/offline-queue';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import observationRoutes from '@/routes/observations';

type Site = {
    id: number;
    code: string;
    name: string;
    areas: { id: number; name: string }[];
};

// crypto.randomUUID needs HTTPS (or localhost); fall back on plain http.
const uuid = () =>
    crypto.randomUUID?.() ??
    '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (
            Number(c) ^
            (crypto.getRandomValues(new Uint8Array(1))[0] &
                (15 >> (Number(c) / 4)))
        ).toString(16),
    );

// A ToggleGroup item styled as a selectable card.
const CHOICE =
    'block h-auto w-full rounded-lg border p-3 text-left whitespace-normal first:rounded-lg last:rounded-lg data-[state=on]:border-green-700 data-[state=on]:bg-green-50 dark:data-[state=on]:bg-green-950';

/**
 * The one-screen report form, built for a phone on site: tap the type, describe it,
 * add photos and location, send.
 */
export default function ReportObservation({
    sites,
    defaultSiteId,
}: {
    sites: Site[];
    defaultSiteId: number | null;
}) {
    const { t } = useTranslation();
    const [locating, setLocating] = useState(false);
    const [locationError, setLocationError] = useState<string | null>(null);
    const form = useForm({
        type: '',
        site_id: String(
            defaultSiteId ?? (sites.length === 1 ? sites[0].id : ''),
        ),
        area_id: '',
        description: '',
        immediate_action: '',
        potential: 'low',
        observed_at: localDateTime(),
        anonymous: false,
        latitude: '',
        longitude: '',
        photos: [] as File[],
    });
    const [waiting, setWaiting] = useState(0);

    // Send anything queued while offline: now, and whenever the signal comes back.
    useEffect(() => {
        const send = async () => {
            if (!navigator.onLine) {
                setWaiting((await queued()).length);

                return;
            }

            const result = await flush();
            setWaiting((await queued()).length);

            if (result.sent > 0) {
                toast.success(
                    t(':n queued report(s) sent. Thank you.', {
                        n: result.sent,
                    }),
                );
            }

            if (result.rejected > 0) {
                toast.error(
                    t(':n queued report(s) could not be accepted.', {
                        n: result.rejected,
                    }),
                );
            }

            if (result.signInNeeded) {
                toast.warning(
                    t('Sign in again to send the reports saved on this phone.'),
                );
            }
        };

        void send();
        window.addEventListener('online', send);

        return () => window.removeEventListener('online', send);
    }, [t]);
    const areas =
        sites.find((s) => String(s.id) === form.data.site_id)?.areas ?? [];

    const locate = () => {
        if (!navigator.geolocation) {
            setLocationError(t('This device cannot share its location.'));

            return;
        }

        setLocating(true);
        setLocationError(null);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                form.setData((data) => ({
                    ...data,
                    latitude: coords.latitude.toFixed(7),
                    longitude: coords.longitude.toFixed(7),
                }));
                setLocating(false);
            },
            () => {
                setLocationError(
                    t('Location not available. Check the browser permission.'),
                );
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    const clear = () =>
        form.setData((data) => ({
            ...data,
            type: '',
            area_id: '',
            description: '',
            immediate_action: '',
            potential: 'low',
            observed_at: localDateTime(),
            anonymous: false,
            latitude: '',
            longitude: '',
            photos: [],
        }));

    // The report as sent: the time as an instant (the server stores UTC) and a client_ref, so a
    // report queued offline and sent twice is stored once.
    const payload = (clientRef: string) => ({
        ...form.data,
        observed_at: new Date(form.data.observed_at).toISOString(),
        client_ref: clientRef,
    });

    // No signal: keep the report on the phone; it is sent when the signal returns.
    const queue = async (clientRef: string) => {
        const { photos, ...fields } = payload(clientRef);
        await enqueue({
            url: observationRoutes.store.url(),
            fields: Object.fromEntries(
                Object.entries(fields).map(([k, v]) => [
                    k,
                    typeof v === 'boolean' ? (v ? '1' : '0') : String(v),
                ]),
            ),
            photos,
            queuedAt: new Date().toISOString(),
        });
        setWaiting((await queued()).length);
        clear();
        toast.info(
            t(
                'No signal: the report is saved on this phone and will send automatically.',
            ),
        );
    };

    const submit = () => {
        const clientRef = uuid();

        if (!navigator.onLine) {
            void queue(clientRef);

            return;
        }

        form.transform(() => payload(clientRef));
        form.post(observationRoutes.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: clear,
            onNetworkError: () => void queue(clientRef),
        });
    };

    return (
        <>
            <Head title={t('Report')} />
            <form
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                className="mx-auto flex w-full max-w-xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                {waiting > 0 && (
                    <div
                        role="status"
                        className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <CloudOff className="size-4 shrink-0" />
                        {t(
                            ':n report(s) saved on this phone, waiting for signal to send.',
                            { n: waiting },
                        )}
                    </div>
                )}
                <div>
                    <h1 className="text-xl font-semibold">
                        {t('Report an observation')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Seen something unsafe, a near miss or good practice? Tell us here.',
                        )}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label id="obs-type-label">
                        {t('What did you see?')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <ToggleGroup
                        type="single"
                        aria-labelledby="obs-type-label"
                        value={form.data.type}
                        // Radix clears the value when the chosen item is tapped again; keep it.
                        onValueChange={(value) =>
                            value && form.setData('type', value)
                        }
                        className="grid gap-2"
                    >
                        {OBSERVATION_TYPES.map((type) => (
                            <ToggleGroupItem
                                key={type.value}
                                value={type.value}
                                className={CHOICE}
                            >
                                <span className="block font-medium">
                                    {t(type.label)}
                                </span>
                                <span className="block text-sm font-normal text-muted-foreground">
                                    {t(type.hint)}
                                </span>
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="obs-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="obs-site"
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
                        <Label htmlFor="obs-area">{t('Area')}</Label>
                        <SelectField
                            id="obs-area"
                            value={form.data.area_id}
                            onChange={(e) =>
                                form.setData('area_id', e.target.value)
                            }
                            disabled={areas.length === 0}
                        >
                            <option value="">{t('Not sure / other')}</option>
                            {areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.area_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="obs-when">
                            {t('When')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DateTimePicker
                            id="obs-when"
                            value={form.data.observed_at}
                            onChange={(value) =>
                                form.setData('observed_at', value)
                            }
                        />
                        <InputError message={form.errors.observed_at} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="obs-description">
                        {t('Describe it')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="obs-description"
                        rows={4}
                        placeholder={t(
                            'What, where exactly, and who could be hurt?',
                        )}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                    <InputError message={form.errors.description} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="obs-immediate">
                        {t('What did you do about it?')}
                    </Label>
                    <Textarea
                        id="obs-immediate"
                        rows={2}
                        placeholder={t(
                            'e.g. barricaded the area, told the supervisor',
                        )}
                        value={form.data.immediate_action}
                        onChange={(e) =>
                            form.setData('immediate_action', e.target.value)
                        }
                    />
                    <InputError message={form.errors.immediate_action} />
                </div>

                <div className="grid gap-2">
                    <Label id="obs-potential-label">
                        {t('How bad could it have been?')}
                    </Label>
                    <ToggleGroup
                        type="single"
                        aria-labelledby="obs-potential-label"
                        value={form.data.potential}
                        onValueChange={(value) =>
                            value && form.setData('potential', value)
                        }
                        className="grid grid-cols-3 items-stretch gap-2"
                    >
                        {POTENTIALS.map((p) => (
                            <ToggleGroupItem
                                key={p.value}
                                value={p.value}
                                className={cn(CHOICE, 'p-2 text-center')}
                            >
                                <span className="block text-sm font-medium">
                                    {t(p.label)}
                                </span>
                                <span className="block text-xs font-normal text-muted-foreground">
                                    {t(p.hint)}
                                </span>
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <InputError message={form.errors.potential} />
                </div>

                <PhotoPicker
                    photos={form.data.photos}
                    onChange={(photos) => form.setData('photos', photos)}
                    errors={form.errors}
                />

                <div className="grid gap-2">
                    <span className="text-sm font-medium">{t('Location')}</span>
                    {form.data.latitude ? (
                        <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                            <span className="flex items-center gap-2">
                                <MapPin className="size-4 text-green-700" />
                                {form.data.latitude}, {form.data.longitude}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    form.setData((data) => ({
                                        ...data,
                                        latitude: '',
                                        longitude: '',
                                    }))
                                }
                            >
                                {t('Remove')}
                            </Button>
                        </div>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={locate}
                            disabled={locating}
                        >
                            {locating ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <MapPin />
                            )}
                            {t('Add my location')}
                        </Button>
                    )}
                    <InputError
                        message={
                            locationError ??
                            form.errors.latitude ??
                            form.errors.longitude
                        }
                    />
                </div>

                <Label className="flex items-start gap-3 rounded-lg border p-3 leading-snug font-normal">
                    <Checkbox
                        checked={form.data.anonymous}
                        onCheckedChange={(checked) =>
                            form.setData('anonymous', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <span>
                        <span className="block font-medium">
                            {t('Report anonymously')}
                        </span>
                        <span className="block text-muted-foreground">
                            {t(
                                'Your name is not stored with this report, nor in its history.',
                            )}
                        </span>
                    </span>
                </Label>

                <Button
                    type="submit"
                    size="lg"
                    disabled={form.processing}
                    className="bg-green-700 text-white hover:bg-green-800"
                >
                    {form.processing ? (
                        <LoaderCircle className="animate-spin" />
                    ) : (
                        <Send />
                    )}
                    {form.progress
                        ? t('Uploading :p%', {
                              p: form.progress.percentage ?? 0,
                          })
                        : t('Send report')}
                </Button>
            </form>
        </>
    );
}

ReportObservation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: observationRoutes.create() },
    ],
};
