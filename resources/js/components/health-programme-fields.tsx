import type { InertiaFormProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SelectField } from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { HEALTH_HAZARDS } from '@/lib/hse';

export const blankProgramme = {
    site_id: '',
    name: '',
    hazard: '',
    chemical_id: '',
    schedule_ii: false,
    exam_interval_months: '12',
    monitoring_interval_months: '',
    exposure_limit: '',
    unit: '',
    active: true,
};

export type ProgrammeFormData = typeof blankProgramme;

/**
 * The legal defaults for a hazard: noise is tested yearly against 85 dB(A) and reassessed every
 * 5 years; a Schedule II chemical is examined yearly and monitored every 6 months.
 */
const defaultsFor = (hazard: string, scheduleII: boolean) =>
    hazard === 'noise'
        ? {
              exam_interval_months: '12',
              monitoring_interval_months: '60',
              exposure_limit: '85',
              unit: 'dB(A)',
          }
        : {
              exam_interval_months: '12',
              monitoring_interval_months: scheduleII ? '6' : '',
              exposure_limit: '',
              unit: 'mg/m³',
          };

/**
 * The fields of a health programme, shared by the create and edit dialogs. The hazard is chosen
 * once, when the programme is created.
 */
export function HealthProgrammeFields({
    form,
    sites,
    chemicals,
    editing = false,
}: {
    form: InertiaFormProps<ProgrammeFormData>;
    sites: { id: number; code: string; name: string }[];
    chemicals: { id: number; site_id: number; name: string }[];
    editing?: boolean;
}) {
    const { t } = useTranslation();
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="hp-name">
                    {t('Name')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="hp-name"
                    placeholder={t('e.g. Noise: concrete breaking and cutting')}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
                <InputError message={errors.name} />
            </div>
            {!editing && (
                <div className="grid gap-2">
                    <Label id="hp-hazard-label">
                        {t('Exposure')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        aria-labelledby="hp-hazard-label"
                        value={data.hazard}
                        onValueChange={(v) =>
                            v &&
                            setData((d) => ({
                                ...d,
                                hazard: v,
                                ...defaultsFor(v, d.schedule_ii),
                            }))
                        }
                        className="justify-start"
                    >
                        {HEALTH_HAZARDS.map((h) => (
                            <ToggleGroupItem key={h.value} value={h.value}>
                                {t(h.label)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <InputError message={errors.hazard} />
                </div>
            )}
            {!editing && sites.length > 1 && (
                <div className="grid gap-2">
                    <Label htmlFor="hp-site">
                        {t('Site')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <SelectField
                        id="hp-site"
                        value={data.site_id}
                        onChange={(e) =>
                            setData((d) => ({
                                ...d,
                                site_id: e.target.value,
                                chemical_id: '',
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
                    <InputError message={errors.site_id} />
                </div>
            )}
            {data.hazard === 'chemical' && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="hp-chemical">{t('Chemical')}</Label>
                        <SelectField
                            id="hp-chemical"
                            value={data.chemical_id}
                            onChange={(e) =>
                                setData('chemical_id', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Not in the chemical register')}
                            </option>
                            {chemicals
                                .filter(
                                    (c) => String(c.site_id) === data.site_id,
                                )
                                .map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={errors.chemical_id} />
                    </div>
                    <Label className="flex items-start gap-3 font-normal sm:col-span-2">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.schedule_ii}
                            onCheckedChange={(checked) =>
                                setData((d) => ({
                                    ...d,
                                    schedule_ii: checked === true,
                                    ...(checked === true
                                        ? {
                                              exam_interval_months: '12',
                                              monitoring_interval_months: '6',
                                          }
                                        : {}),
                                }))
                            }
                        />
                        <span>
                            {t('USECHH Schedule II chemical')}
                            <span className="block text-xs text-muted-foreground">
                                {t(
                                    'Such as free crystalline silica, lead, benzene, isocyanates, mineral oil, pesticides or asbestos. Medical surveillance by an occupational health doctor at least every 12 months, exposure monitoring at least every 6.',
                                )}
                            </span>
                        </span>
                    </Label>
                </>
            )}
            <div className="grid gap-2">
                <Label htmlFor="hp-exam">
                    {t('Examine every (months)')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="hp-exam"
                    inputMode="numeric"
                    value={data.exam_interval_months}
                    onChange={(e) =>
                        setData('exam_interval_months', e.target.value)
                    }
                />
                <InputError message={errors.exam_interval_months} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="hp-monitor">
                    {t('Monitor exposure every (months)')}
                </Label>
                <Input
                    id="hp-monitor"
                    inputMode="numeric"
                    placeholder={t('Not required')}
                    value={data.monitoring_interval_months}
                    onChange={(e) =>
                        setData('monitoring_interval_months', e.target.value)
                    }
                />
                <InputError message={errors.monitoring_interval_months} />
            </div>
            <div className="grid grid-cols-[1fr_7rem] gap-2 sm:col-span-2">
                <div className="grid gap-2">
                    <Label htmlFor="hp-limit">{t('Exposure limit')}</Label>
                    <Input
                        id="hp-limit"
                        inputMode="decimal"
                        value={data.exposure_limit}
                        onChange={(e) =>
                            setData('exposure_limit', e.target.value)
                        }
                    />
                    <InputError message={errors.exposure_limit} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="hp-unit">{t('Unit')}</Label>
                    <Input
                        id="hp-unit"
                        value={data.unit}
                        onChange={(e) => setData('unit', e.target.value)}
                    />
                </div>
            </div>
            {editing && (
                <Label className="flex items-center gap-2 font-normal sm:col-span-2">
                    <Checkbox
                        checked={data.active}
                        onCheckedChange={(checked) =>
                            setData('active', checked === true)
                        }
                    />
                    {t('Active')}
                </Label>
            )}
        </div>
    );
}
