import type { InertiaFormProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PeoplePicker } from '@/components/people-picker';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { DOCUMENT_TYPES } from '@/lib/hse';

export type DocumentFormData = {
    number: string;
    title: string;
    type: string;
    owner_id: string;
    review_interval_months: string;
    clause_ids: number[];
};

/** The fields of a controlled document, shared by the create and edit dialogs. */
export function DocumentFields({
    form,
    people,
    clauses,
}: {
    form: InertiaFormProps<DocumentFormData>;
    people: { id: number; name: string }[];
    clauses: { id: number; number: string; title: string }[];
}) {
    const { t } = useTranslation();
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="doc-number">
                    {t('Document no.')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="doc-number"
                    placeholder={t('e.g. HSE-PRO-007')}
                    value={data.number}
                    onChange={(e) => setData('number', e.target.value)}
                />
                <InputError message={errors.number} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="doc-type">{t('Type')}</Label>
                <SelectField
                    id="doc-type"
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value)}
                >
                    {DOCUMENT_TYPES.map((d) => (
                        <option key={d.value} value={d.value}>
                            {t(d.label)}
                        </option>
                    ))}
                </SelectField>
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="doc-title">
                    {t('Title')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="doc-title"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                />
                <InputError message={errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="doc-owner">{t('Owner')}</Label>
                <SelectField
                    id="doc-owner"
                    value={data.owner_id}
                    onChange={(e) => setData('owner_id', e.target.value)}
                >
                    <option value="">{t('No owner')}</option>
                    {people.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </SelectField>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="doc-interval">
                    {t('Review every (months)')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="doc-interval"
                    inputMode="numeric"
                    value={data.review_interval_months}
                    onChange={(e) =>
                        setData('review_interval_months', e.target.value)
                    }
                />
                <InputError message={errors.review_interval_months} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="doc-clauses">{t('ISO 45001 clauses')}</Label>
                <PeoplePicker
                    id="doc-clauses"
                    placeholder="Filter clauses…"
                    people={clauses.map((c) => ({
                        id: c.id,
                        name: `${c.number} ${c.title}`,
                    }))}
                    value={data.clause_ids}
                    onChange={(ids) => setData('clause_ids', ids)}
                />
            </div>
        </div>
    );
}
