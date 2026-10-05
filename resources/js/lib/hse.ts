// Labels for the enum values the server validates (Observation::TYPES, Action::CONTROL_LEVELS, ...).

export const OBSERVATION_TYPES = [
    {
        value: 'unsafe-condition',
        label: 'Unsafe condition',
        hint: 'Something in the workplace that could hurt someone',
    },
    {
        value: 'unsafe-act',
        label: 'Unsafe act',
        hint: 'Someone working in a way that could hurt them or others',
    },
    {
        value: 'near-miss',
        label: 'Near miss',
        hint: 'Something happened, and nobody was hurt this time',
    },
    {
        value: 'environmental',
        label: 'Environmental',
        hint: 'Spill, leak, waste, dust or noise',
    },
    {
        value: 'positive',
        label: 'Good practice',
        hint: 'Someone working safely, worth recognising',
    },
] as const;

export const POTENTIALS = [
    { value: 'low', label: 'Low', hint: 'First aid at most' },
    { value: 'medium', label: 'Medium', hint: 'Could need medical treatment' },
    { value: 'high', label: 'High', hint: 'Could cause serious injury' },
] as const;

/** The hierarchy of controls, most effective first. */
export const CONTROL_LEVELS = [
    { value: 'elimination', label: 'Elimination' },
    { value: 'substitution', label: 'Substitution' },
    { value: 'engineering', label: 'Engineering control' },
    { value: 'administrative', label: 'Administrative control' },
    { value: 'ppe', label: 'PPE' },
] as const;

export const PRIORITIES = ['low', 'medium', 'high'] as const;

export const labelOf = (
    list: readonly { value: string; label: string }[],
    value: string,
) => list.find((item) => item.value === value)?.label ?? value;

export const INCIDENT_TYPES = [
    { value: 'injury', label: 'Injury' },
    { value: 'illness', label: 'Occupational illness' },
    { value: 'property-damage', label: 'Property damage' },
    { value: 'environmental-release', label: 'Environmental release' },
    { value: 'vehicle', label: 'Vehicle' },
    { value: 'fire', label: 'Fire' },
    { value: 'security', label: 'Security' },
] as const;

/** Least to most severe; an incident's classification is the worst of its injured people. */
export const CLASSIFICATIONS = [
    { value: 'no-injury', label: 'No injury' },
    { value: 'first-aid', label: 'First aid' },
    { value: 'medical', label: 'Medical treatment' },
    { value: 'restricted', label: 'Restricted work' },
    { value: 'lost-time', label: 'Lost time' },
    { value: 'fatality', label: 'Fatality' },
] as const;

export const TREATMENTS = CLASSIFICATIONS.filter(
    (c) => c.value !== 'no-injury',
);

export const PERSON_ROLES = [
    { value: 'injured', label: 'Injured' },
    { value: 'witness', label: 'Witness' },
    { value: 'involved', label: 'Involved' },
] as const;

export const ROOT_CAUSE_CATEGORIES = [
    { value: 'procedures', label: 'Procedures' },
    { value: 'training', label: 'Training and competence' },
    { value: 'equipment', label: 'Equipment and tools' },
    { value: 'supervision', label: 'Supervision' },
    { value: 'work-environment', label: 'Work environment' },
    { value: 'human-factors', label: 'Human factors' },
    { value: 'design', label: 'Design' },
] as const;

export const RISK_TYPES = [
    { value: 'hira', label: 'HIRA' },
    { value: 'jsa', label: 'Job safety analysis' },
    { value: 'coshh', label: 'COSHH (hazardous substances)' },
    { value: 'manual-handling', label: 'Manual handling' },
] as const;

export const LIKELIHOOD = [
    { value: 1, label: 'Rare' },
    { value: 2, label: 'Unlikely' },
    { value: 3, label: 'Possible' },
    { value: 4, label: 'Likely' },
    { value: 5, label: 'Almost certain' },
] as const;

export const SEVERITY = [
    { value: 1, label: 'Insignificant' },
    { value: 2, label: 'Minor' },
    { value: 3, label: 'Moderate' },
    { value: 4, label: 'Major' },
    { value: 5, label: 'Catastrophic' },
] as const;

/** Same bands as RiskAssessment::band(); 15 and above blocks approval. */
export const RESIDUAL_LIMIT = 15;

export const riskBand = (score: number) =>
    score >= RESIDUAL_LIMIT
        ? 'extreme'
        : score >= 10
          ? 'high'
          : score >= 5
            ? 'medium'
            : 'low';

export const RESPONSE_TYPES = [
    { value: 'yes-no-na', label: 'Yes / No / N/A' },
    { value: 'rating', label: 'Rating 1–5' },
    { value: 'number', label: 'Number (with limits)' },
    { value: 'text', label: 'Text (recorded only)' },
] as const;

export const PERMIT_TYPES = [
    { value: 'hot-work', label: 'Hot work' },
    { value: 'confined-space', label: 'Confined space' },
    { value: 'work-at-height', label: 'Work at height' },
    { value: 'electrical', label: 'Electrical' },
    { value: 'excavation', label: 'Excavation' },
    { value: 'lifting', label: 'Lifting' },
    { value: 'general', label: 'General' },
] as const;

export const DOCUMENT_TYPES = [
    { value: 'policy', label: 'Policy' },
    { value: 'procedure', label: 'Safe work procedure' },
    { value: 'work-instruction', label: 'Work instruction' },
    { value: 'emergency-plan', label: 'Emergency plan' },
    { value: 'form', label: 'Form' },
    { value: 'record', label: 'Record' },
] as const;

export const FINDING_TYPES = [
    { value: 'major-nonconformity', label: 'Major nonconformity' },
    { value: 'minor-nonconformity', label: 'Minor nonconformity' },
    { value: 'observation', label: 'Observation' },
    { value: 'opportunity', label: 'Opportunity for improvement' },
] as const;

/** GHS hazard pictograms (Chemical::HAZARDS). */
export const GHS_HAZARDS = [
    { value: 'explosive', label: 'Explosive' },
    { value: 'flammable', label: 'Flammable' },
    { value: 'oxidising', label: 'Oxidising' },
    { value: 'gas-under-pressure', label: 'Gas under pressure' },
    { value: 'corrosive', label: 'Corrosive' },
    { value: 'acute-toxicity', label: 'Acute toxicity' },
    { value: 'health-hazard', label: 'Serious health hazard' },
    { value: 'harmful', label: 'Harmful / irritant' },
    { value: 'environment', label: 'Hazardous to the environment' },
] as const;

/** OSHA 300 column M. */
export const ILLNESS_TYPES = [
    { value: 'skin-disorder', label: 'Skin disorder' },
    { value: 'respiratory', label: 'Respiratory condition' },
    { value: 'poisoning', label: 'Poisoning' },
    { value: 'hearing-loss', label: 'Hearing loss' },
    { value: 'other-illness', label: 'All other illnesses' },
] as const;

/** A rating passes at this score or above unless the question sets its own (ChecklistItem::RATING_PASS). */
export const RATING_PASS = 3;

/**
 * What passes for a checklist question: "12–16", "≥ 2", "≤ 85" for numbers, "3+" for ratings.
 */
export const acceptable = (q: {
    response_type: string;
    min: string | null;
    max: string | null;
}) => {
    if (q.response_type === 'rating') {
        return `${q.min === null ? RATING_PASS : Number(q.min)}+`;
    }

    if (q.response_type !== 'number') {
        return '';
    }

    return q.min !== null && q.max !== null
        ? `${Number(q.min)}–${Number(q.max)}`
        : q.min !== null
          ? `≥ ${Number(q.min)}`
          : q.max !== null
            ? `≤ ${Number(q.max)}`
            : '';
};
