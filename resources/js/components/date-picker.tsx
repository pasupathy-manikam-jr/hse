import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// "2026-10-05" <-> a local Date, so a calendar day never shifts with the time zone.
const toDate = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(y, m - 1, d);
};

const toValue = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * shadcn date picker (Popover + Calendar) holding a "Y-m-d" string, as the server expects.
 */
export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pick a date',
    className,
    'aria-invalid': ariaInvalid,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    'aria-invalid'?: boolean;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-invalid={ariaInvalid}
                    className={cn(
                        'w-full justify-start font-normal',
                        !value && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarDays className="text-muted-foreground" />
                    {value ? date(value) : t(placeholder)}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    captionLayout="dropdown"
                    selected={value ? toDate(value) : undefined}
                    defaultMonth={value ? toDate(value) : undefined}
                    onSelect={(picked) => {
                        onChange(picked ? toValue(picked) : '');
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

/**
 * Date picker plus a time input, holding "Y-m-dTH:i" in the user's own time zone.
 * Convert with `new Date(value).toISOString()` before sending, so the server stores the instant.
 */
export function DateTimePicker({
    id,
    value,
    onChange,
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();
    const day = value.slice(0, 10);
    const time = value.slice(11, 16);

    return (
        <div className="grid grid-cols-[1fr_8.5rem] gap-2">
            <DatePicker
                id={id}
                value={day}
                onChange={(next) => onChange(`${next}T${time || '00:00'}`)}
            />
            <Input
                type="time"
                aria-label={t('Time')}
                value={time}
                onChange={(e) => onChange(`${day}T${e.target.value}`)}
            />
        </div>
    );
}

/** "2026-10-05T14:30" for now (or a given instant), in the browser's own time zone. */
export const localDateTime = (value?: string) => {
    const date = value ? new Date(value) : new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
};
