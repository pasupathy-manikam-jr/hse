import {
    Children,
    Fragment,
    isValidElement,
    type ChangeEvent,
    type ComponentProps,
    type ReactElement,
    type ReactNode,
} from 'react';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** Radix items can't use "" as a value, so the "All …" / "None" option is stored under this key. */
const EMPTY = '__empty__';

type Option = { value: string; label: ReactNode; disabled?: boolean };
type Group = { label: ReactNode; options: Option[] };

type OptionProps = {
    value?: string | number;
    children?: ReactNode;
    disabled?: boolean;
    label?: ReactNode;
};

/** Flattens <option>/<optgroup> children (including arrays, fragments and falsy entries). */
function collect(children: ReactNode): (Option | Group)[] {
    return Children.toArray(children).flatMap((child) => {
        if (!isValidElement(child)) {
            return [];
        }

        const element = child as ReactElement<OptionProps>;

        if (element.type === Fragment) {
            return collect(element.props.children);
        }

        if (element.type === 'optgroup') {
            return [
                {
                    label: element.props.label,
                    options: collect(element.props.children) as Option[],
                },
            ];
        }

        // Like a native <option>, a missing value falls back to the option's text.
        const { value, children: text } = element.props;
        const fallback =
            typeof text === 'string' || typeof text === 'number' ? text : '';

        return [
            {
                value: String(value ?? fallback),
                label: element.props.children,
                disabled: element.props.disabled,
            },
        ];
    });
}

/**
 * The app's dropdown: a shadcn (Radix) Select that takes the same props as a native <select>
 * (value, onChange with e.target.value, <option>/<optgroup> children, id, required, disabled),
 * so forms and filters keep their existing code. An option with value "" acts as the
 * placeholder when the field is required, and as a normal choice ("All …") otherwise.
 */
export function SelectField({
    value,
    onChange,
    children,
    className,
    id,
    required,
    disabled,
    name,
    placeholder,
    'aria-label': ariaLabel,
    'aria-invalid': ariaInvalid,
}: Omit<ComponentProps<'select'>, 'placeholder'> & { placeholder?: string }) {
    const entries = collect(children);
    const options = entries.flatMap((e) => ('options' in e ? e.options : [e]));
    const empty = options.find((o) => o.value === '');
    const current = value === undefined || value === null ? '' : String(value);

    const change = (next: string) => {
        const nextValue = next === EMPTY ? '' : next;
        onChange?.({
            target: { value: nextValue, name },
            currentTarget: { value: nextValue, name },
        } as unknown as ChangeEvent<HTMLSelectElement>);
    };

    const item = (o: Option) =>
        o.value === '' && required ? null : (
            <SelectItem
                key={o.value}
                value={o.value === '' ? EMPTY : o.value}
                disabled={o.disabled}
            >
                {o.label}
            </SelectItem>
        );

    return (
        <Select
            value={current === '' ? (required || !empty ? '' : EMPTY) : current}
            onValueChange={change}
            required={required}
            disabled={disabled}
            name={name}
        >
            <SelectTrigger
                id={id}
                aria-label={ariaLabel}
                aria-invalid={ariaInvalid}
                className={cn('w-full', className)}
            >
                <SelectValue
                    placeholder={
                        placeholder ?? (empty ? empty.label : undefined)
                    }
                />
            </SelectTrigger>
            <SelectContent>
                {entries.map((entry, i) =>
                    'options' in entry ? (
                        <SelectGroup key={`group-${i}`}>
                            <SelectLabel>{entry.label}</SelectLabel>
                            {entry.options.map(item)}
                        </SelectGroup>
                    ) : (
                        item(entry)
                    ),
                )}
            </SelectContent>
        </Select>
    );
}
