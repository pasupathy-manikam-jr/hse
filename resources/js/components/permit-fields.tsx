import type { InertiaFormProps } from '@inertiajs/react';
import { DateTimePicker } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { PeoplePicker } from '@/components/people-picker';
import { SelectField } from '@/components/select-field';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { PERMIT_TYPES } from '@/lib/hse';

export type PermitFormData = {
    type: string;
    site_id: string;
    area_id: string;
    risk_assessment_id: string;
    contractor_id: string;
    description: string;
    valid_from: string;
    valid_to: string;
    worker_ids: number[];
};

export type PermitOptions = {
    sites: {
        id: number;
        code: string;
        name: string;
        areas: { id: number; name: string }[];
    }[];
    maxHours: number;
    assessments: {
        id: number;
        site_id: number;
        number: string;
        title: string;
    }[];
    contractors: {
        id: number;
        name: string;
        approved: boolean;
        insurance_expires_on: string | null;
    }[];
    people: {
        id: number;
        name: string;
        site_id: number | null;
        contractor_id: number | null;
    }[];
};

/** Send the window as instants, so the server (UTC) stores what the user meant. */
export const permitPayload = (data: PermitFormData) => ({
    ...data,
    valid_from: new Date(data.valid_from).toISOString(),
    valid_to: new Date(data.valid_to).toISOString(),
});

/**
 * The fields of a permit request, shared by the request page and the edit dialog.
 */
export function PermitFields({
    form,
    options,
    siteLocked = false,
}: {
    form: InertiaFormProps<PermitFormData>;
    options: PermitOptions;
    siteLocked?: boolean;
}) {
    const { t } = useTranslation();
    const { data, setData, errors } = form;
    const site = options.sites.find((s) => String(s.id) === data.site_id);
    const contractorName = (id: number | null) =>
        options.contractors.find((c) => c.id === id)?.name ?? null;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="ptw-type">
                    {t('Type of work')}
                    <span className="text-destructive">*</span>
                </Label>
                <SelectField
                    id="ptw-type"
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value)}
                >
                    <option value="">{t('Select type')}</option>
                    {PERMIT_TYPES.map((p) => (
                        <option key={p.value} value={p.value}>
                            {t(p.label)}
                        </option>
                    ))}
                </SelectField>
                <InputError message={errors.type} />
            </div>
            {!siteLocked && options.sites.length > 1 && (
                <div className="grid gap-2">
                    <Label htmlFor="ptw-site">
                        {t('Site')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="ptw-site"
                        value={data.site_id}
                        onChange={(e) =>
                            form.setData((d) => ({
                                ...d,
                                site_id: e.target.value,
                                area_id: '',
                                risk_assessment_id: '',
                            }))
                        }
                    >
                        <option value="">{t('Select site')}</option>
                        {options.sites.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.code} · {s.name}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={errors.site_id} />
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor="ptw-area">{t('Area')}</Label>
                <SelectField
                    id="ptw-area"
                    value={data.area_id}
                    onChange={(e) => setData('area_id', e.target.value)}
                    disabled={!site || site.areas.length === 0}
                >
                    <option value="">{t('Whole site')}</option>
                    {site?.areas.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.name}
                        </option>
                    ))}
                </SelectField>
                <InputError message={errors.area_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="ptw-ra">
                    {t('Risk assessment / JSA')}
                    <span className="text-destructive">*</span>
                </Label>
                <SelectField
                    id="ptw-ra"
                    value={data.risk_assessment_id}
                    onChange={(e) =>
                        setData('risk_assessment_id', e.target.value)
                    }
                >
                    <option value="">{t('Select approved assessment')}</option>
                    {options.assessments
                        .filter((a) => String(a.site_id) === data.site_id)
                        .map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.number} · {a.title}
                            </option>
                        ))}
                </SelectField>
                <InputError message={errors.risk_assessment_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="ptw-from">
                    {t('Valid from')}
                    <span className="text-destructive">*</span>
                </Label>
                <DateTimePicker
                    id="ptw-from"
                    value={data.valid_from}
                    onChange={(value) => setData('valid_from', value)}
                />
                <InputError message={errors.valid_from} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="ptw-to">
                    {t('Valid to')}
                    <span className="text-destructive">*</span>
                </Label>
                <DateTimePicker
                    id="ptw-to"
                    value={data.valid_to}
                    onChange={(value) => setData('valid_to', value)}
                />
                <InputError message={errors.valid_to} />
                <p className="text-xs text-muted-foreground">
                    {t('One shift: at most :h hours.', { h: options.maxHours })}
                </p>
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="ptw-description">
                    {t('Work to be done')}
                    <span className="text-destructive">*</span>
                </Label>
                <Textarea
                    id="ptw-description"
                    rows={3}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="ptw-contractor">{t('Contractor')}</Label>
                <SelectField
                    id="ptw-contractor"
                    value={data.contractor_id}
                    onChange={(e) => setData('contractor_id', e.target.value)}
                >
                    <option value="">{t('Own staff')}</option>
                    {options.contractors.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                            {c.approved ? '' : ` (${t('not approved')})`}
                        </option>
                    ))}
                </SelectField>
                <InputError message={errors.contractor_id} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="ptw-workers">
                    {t('Workers')}
                    <span className="text-destructive">*</span>
                </Label>
                <PeoplePicker
                    id="ptw-workers"
                    people={options.people.filter(
                        (p) =>
                            p.site_id === null ||
                            String(p.site_id) === data.site_id,
                    )}
                    value={data.worker_ids}
                    onChange={(ids) => setData('worker_ids', ids)}
                    note={(id) =>
                        contractorName(
                            options.people.find((p) => p.id === id)
                                ?.contractor_id ?? null,
                        )
                    }
                />
                <InputError message={errors.worker_ids} />
            </div>
        </div>
    );
}
