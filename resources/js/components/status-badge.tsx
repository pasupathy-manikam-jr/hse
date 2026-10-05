import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    approved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    hired: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    pending: 'border-amber-200 bg-amber-50 text-amber-700',
    pending_approval: 'border-amber-200 bg-amber-50 text-amber-700',
    renewed: 'border-teal-200 bg-teal-50 text-teal-700',
    screening: 'border-amber-200 bg-amber-50 text-amber-700',
    scheduled: 'border-blue-200 bg-blue-50 text-blue-700',
    new: 'border-sky-200 bg-sky-50 text-sky-700',
    interview: 'border-violet-200 bg-violet-50 text-violet-700',
    offer: 'border-orange-200 bg-orange-50 text-orange-700',
    rejected: 'border-red-200 bg-red-50 text-red-700',
    urgent: 'border-red-200 bg-red-50 text-red-700',
    cancelled: 'border-red-200 bg-red-50 text-red-700',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    inactive: 'border-gray-200 bg-gray-50 text-gray-600',
    probation: 'border-amber-200 bg-amber-50 text-amber-700',
    terminated: 'border-red-200 bg-red-50 text-red-700',
    upcoming: 'border-blue-200 bg-blue-50 text-blue-700',
    expired: 'border-gray-200 bg-gray-50 text-gray-600',
    available: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    assigned: 'border-blue-200 bg-blue-50 text-blue-700',
    under_maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
    maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
    disposed: 'border-red-200 bg-red-50 text-red-700',
    half_day: 'border-amber-200 bg-amber-50 text-amber-700',
    absent: 'border-red-200 bg-red-50 text-red-700',
    present: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    on_leave: 'border-sky-200 bg-sky-50 text-sky-700',
    draft: 'border-gray-200 bg-gray-50 text-gray-600',
    processing: 'border-blue-200 bg-blue-50 text-blue-700',
    generated: 'border-sky-200 bg-sky-50 text-sky-700',
    sent: 'border-blue-200 bg-blue-50 text-blue-700',
    downloaded: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    published: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    closed: 'border-gray-200 bg-gray-50 text-gray-600',
    open: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    imported: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    replaced: 'border-sky-200 bg-sky-50 text-sky-700',
    skipped: 'border-amber-200 bg-amber-50 text-amber-700',
    in_progress: 'border-blue-200 bg-blue-50 text-blue-700',
    not_started: 'border-gray-200 bg-gray-50 text-gray-600',
    overdue: 'border-red-200 bg-red-50 text-red-700',
    failed: 'border-red-200 bg-red-50 text-red-700',
    passed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    pass: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    fail: 'border-red-200 bg-red-50 text-red-700',
    issued: 'border-amber-200 bg-amber-50 text-amber-700',
    acknowledged: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    verbal: 'border-sky-200 bg-sky-50 text-sky-700',
    written: 'border-amber-200 bg-amber-50 text-amber-700',
    final: 'border-red-200 bg-red-50 text-red-700',
    paid: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    unpaid: 'border-gray-200 bg-gray-50 text-gray-600',
    planned: 'border-sky-200 bg-sky-50 text-sky-700',
    ongoing: 'border-blue-200 bg-blue-50 text-blue-700',
    submitted: 'border-amber-200 bg-amber-50 text-amber-700',
    under_investigation: 'border-violet-200 bg-violet-50 text-violet-700',
    resolved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    dismissed: 'border-gray-200 bg-gray-50 text-gray-600',
    required: 'border-amber-200 bg-amber-50 text-amber-700',
    optional: 'border-gray-200 bg-gray-50 text-gray-600',
    not_required: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    'no-show': 'border-red-200 bg-red-50 text-red-700',
    accepted: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    declined: 'border-red-200 bg-red-50 text-red-700',
    negotiating: 'border-amber-200 bg-amber-50 text-amber-700',
    strong_hire: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    hire: 'border-blue-200 bg-blue-50 text-blue-700',
    maybe: 'border-amber-200 bg-amber-50 text-amber-700',
    reject: 'border-red-200 bg-red-50 text-red-700',
    strong_reject: 'border-red-200 bg-red-50 text-red-700',
    under_review: 'border-amber-200 bg-amber-50 text-amber-700',
    archived: 'border-gray-200 bg-gray-50 text-gray-600',
    exempted: 'border-sky-200 bg-sky-50 text-sky-700',
    tentative: 'border-amber-200 bg-amber-50 text-amber-700',
    late: 'border-amber-200 bg-amber-50 text-amber-700',
    not_attended: 'border-gray-200 bg-gray-50 text-gray-600',
    note: 'border-gray-200 bg-gray-50 text-gray-600',
    discussion: 'border-sky-200 bg-sky-50 text-sky-700',
    decision: 'border-violet-200 bg-violet-50 text-violet-700',
    action_item: 'border-orange-200 bg-orange-50 text-orange-700',
    contacted: 'border-blue-200 bg-blue-50 text-blue-700',
    qualified: 'border-violet-200 bg-violet-50 text-violet-700',
    converted: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    self_enrollment: 'border-blue-200 bg-blue-50 text-blue-700',
    physical: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    virtual: 'border-blue-200 bg-blue-50 text-blue-700',
    quiz: 'border-blue-200 bg-blue-50 text-blue-700',
    practical: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    presentation: 'border-violet-200 bg-violet-50 text-violet-700',
    earning: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    deduction: 'border-red-200 bg-red-50 text-red-700',
    mandatory: 'border-red-200 bg-red-50 text-red-700',
    yes: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    no: 'border-red-200 bg-red-50 text-red-700',
    translated: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    not_translated: 'border-gray-200 bg-gray-50 text-gray-600',
    good: 'border-blue-200 bg-blue-50 text-blue-700',
    fair: 'border-amber-200 bg-amber-50 text-amber-700',
    day_shift: 'border-amber-200 bg-amber-50 text-amber-700',
    night_shift: 'border-slate-300 bg-slate-100 text-slate-700',
    poor: 'border-red-200 bg-red-50 text-red-700',
    // Holiday categories (same colours as the holiday calendar)
    national: 'border-blue-200 bg-blue-50 text-blue-700',
    religious: 'border-violet-200 bg-violet-50 text-violet-700',
    'company-specific': 'border-green-200 bg-green-50 text-green-700',
    regional: 'border-orange-200 bg-orange-50 text-orange-700',
    // Trip advances
    requested: 'border-blue-200 bg-blue-50 text-blue-700',
    reconciled: 'border-purple-200 bg-purple-50 text-purple-700',
    // Job location types
    remote: 'border-blue-200 bg-blue-50 text-blue-700',
    'on-site': 'border-gray-200 bg-gray-50 text-gray-700',
    // Onboarding checklist item categories
    training: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    it_setup: 'border-purple-200 bg-purple-50 text-purple-700',
    documentation: 'border-blue-200 bg-blue-50 text-blue-700',
    facilities: 'border-orange-200 bg-orange-50 text-orange-700',
};

// "under_maintenance" -> "Under Maintenance"
const label = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

/** `label` overrides the text when a status reuses another key's colour (e.g. a red "Required"). */
export function StatusBadge({
    status,
    label: text,
}: {
    status: string;
    label?: string;
}) {
    const { t } = useTranslation();

    return (
        <Badge
            variant="outline"
            className={cn(
                'shrink-0',
                text === undefined && 'capitalize',
                statusStyles[status.toLowerCase().replace(/ /g, '_')],
            )}
        >
            {t(text ?? label(status))}
        </Badge>
    );
}
