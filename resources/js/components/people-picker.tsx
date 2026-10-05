import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Choose several items from a filterable checkbox list (permit workers, attendees, ISO clauses).
 */
export function PeoplePicker({
    id,
    people,
    value,
    onChange,
    note,
    placeholder = 'Filter people…',
}: {
    id: string;
    people: { id: number; name: string }[];
    value: number[];
    onChange: (ids: number[]) => void;
    /** Extra text after a person's name (e.g. their employer). */
    note?: (personId: number) => string | null;
    placeholder?: string;
}) {
    const { t } = useTranslation();
    const [filter, setFilter] = useState('');
    const shown = people.filter((p) =>
        p.name.toLowerCase().includes(filter.toLowerCase()),
    );

    return (
        <div className="grid gap-2 rounded-md border p-2">
            <Input
                id={id}
                placeholder={t(placeholder)}
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
            />
            <ul className="grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
                {shown.map((p) => (
                    <li key={p.id}>
                        <Label className="flex items-center gap-2 rounded px-2 py-1.5 font-normal hover:bg-muted">
                            <Checkbox
                                checked={value.includes(p.id)}
                                onCheckedChange={(checked) =>
                                    onChange(
                                        checked === true
                                            ? [...value, p.id]
                                            : value.filter((v) => v !== p.id),
                                    )
                                }
                            />
                            <span>
                                {p.name}
                                {note?.(p.id) && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {note(p.id)}
                                    </span>
                                )}
                            </span>
                        </Label>
                    </li>
                ))}
                {shown.length === 0 && (
                    <li className="px-2 py-1.5 text-sm text-muted-foreground">
                        {t('Nobody matches.')}
                    </li>
                )}
            </ul>
            <p className="px-1 text-xs text-muted-foreground">
                {t(':n selected', { n: value.length })}
            </p>
        </div>
    );
}
