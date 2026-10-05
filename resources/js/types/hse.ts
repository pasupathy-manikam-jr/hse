type Person = { id: number; name: string };

export type Action = {
    id: number;
    number: string;
    description: string;
    control_level: string;
    priority: 'low' | 'medium' | 'high';
    owner_id: number;
    owner: Person;
    due_on: string;
    status: 'open' | 'done' | 'verified';
    completion_notes: string | null;
    done_at: string | null;
    verifier: Person | null;
    verified_at: string | null;
    rejection_reason: string | null;
};

export type Observation = {
    id: number;
    number: string;
    type: string;
    potential: 'low' | 'medium' | 'high';
    description: string;
    immediate_action: string | null;
    observed_at: string;
    anonymous: boolean;
    latitude: string | null;
    longitude: string | null;
    status: 'open' | 'actioned' | 'closed';
    closed_at: string | null;
    created_at: string;
    site: { id: number; code: string; name?: string };
    area: { id: number; name: string } | null;
    reporter: Person | null;
    closer?: Person | null;
};

export type IncidentPerson = {
    id: number;
    user_id: number | null;
    name: string;
    job_title: string | null;
    role: 'injured' | 'witness' | 'involved';
    treatment: string | null;
    illness_type: string | null;
    privacy_case: boolean;
    body_part: string | null;
    injury_nature: string | null;
    days_lost: number;
    days_restricted: number;
};

export type Incident = {
    id: number;
    number: string;
    site_id: number;
    area_id: number | null;
    risk_assessment_id: number | null;
    type: string;
    title: string;
    description: string;
    immediate_actions: string | null;
    occurred_at: string;
    classification: string;
    recordable: boolean;
    status:
        | 'reported'
        | 'under-investigation'
        | 'actions-in-progress'
        | 'closed';
    investigation_team: string | null;
    sequence_of_events: string | null;
    whys: string[] | null;
    root_cause_category: string | null;
    root_cause: string | null;
    contributing_factors: string | null;
    closed_at: string | null;
    created_at: string;
    site: { id: number; code: string; name?: string };
    area: { id: number; name: string } | null;
};

export type RiskHazard = {
    id: number;
    hazard: string;
    who_at_risk: string | null;
    existing_controls: string | null;
    likelihood: number;
    severity: number;
    additional_controls: string | null;
    residual_likelihood: number;
    residual_severity: number;
    initial_score: number;
    residual_score: number;
};

export type RiskAssessment = {
    id: number;
    number: string;
    revision: number;
    previous_id: number | null;
    site_id: number;
    area_id: number | null;
    type: string;
    title: string;
    activity: string;
    review_due_on: string;
    status: 'draft' | 'approved' | 'superseded';
    review_required: boolean;
    review_reason: string | null;
    approved_at: string | null;
    created_by: number | null;
    site: { id: number; code: string; name?: string };
    area: { id: number; name: string } | null;
};

export type InspectionAnswer = {
    id: number;
    question: string;
    response_type: 'yes-no-na' | 'rating' | 'number' | 'text';
    min: string | null;
    max: string | null;
    critical: boolean;
    answer: string | null;
    passed: boolean | null;
    notes: string | null;
    photos: { id: number }[];
};

export type Inspection = {
    id: number;
    number: string;
    template_name: string;
    site_id: number;
    status: 'in-progress' | 'completed';
    score: number | null;
    notes: string | null;
    completed_at: string | null;
    created_at: string;
    created_by: number | null;
    site: { id: number; code: string; name?: string };
    area: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
};

export type Permit = {
    id: number;
    number: string;
    type: string;
    site_id: number;
    area_id: number | null;
    contractor_id: number | null;
    risk_assessment_id: number;
    description: string;
    valid_from: string;
    valid_to: string;
    precautions: Record<string, boolean> | null;
    status:
        | 'requested'
        | 'approved'
        | 'active'
        | 'suspended'
        | 'closed'
        | 'cancelled';
    status_reason: string | null;
    approved_at: string | null;
    closed_at: string | null;
    created_by: number | null;
    created_at: string;
    site: { id: number; code: string; name?: string };
    area: { id: number; name: string } | null;
    contractor: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
};
