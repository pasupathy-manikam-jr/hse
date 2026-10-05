# HSE — build plan

An open-source HSE (health, safety and environment) management system for sites with field crews: construction, manufacturing, oil & gas, logistics. Built to ISO 45001 (OH&S) with ISO 14001 hooks.

**The differentiator:** one linked chain instead of separate registers (the "automatic record linkage" every commercial review ranks first):

    observation / near miss → incident → investigation (5-Why) → corrective action (verified by someone else) → risk register update → toolbox talk / training

No open-source tool covers this. The "awesome EHS" lists on GitHub are mostly calculators and PPE-detection CV demos. The full suites (EcoOnline, Intelex, Evotix, Enablon, Cority, SafetyCulture) are all commercial.

## What we borrow, and from where

| Source                   | Idea we take                                                                                      | What we leave out                        |
| ------------------------ | ------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| EcoOnline / SafetyQube   | One mobile-first "Report" button for incidents, near misses and observations; anonymous reporting | Chemical/SDS library (phase 6, optional) |
| Evotix                   | Control of work: permit-to-work + LOTO isolations + shift handover in one module                  | No-code form builder                     |
| SafetyCulture (iAuditor) | Inspection checklists from templates; a failed item raises an action on the spot                  | Sensor/IoT integrations                  |
| Intelex / EHS Insight    | OSHA 300/300A/301 log generated from incidents; LTIFR/TRIR on the dashboard                       | Sustainability/ESG reporting             |
| SDS Manager EHS          | Actions need independent verification (closer ≠ owner); append-only audit trail                   | —                                        |
| Cority                   | Worker training/competency matrix with expiry blocks permit issue                                 | Occupational health/exposure monitoring  |
| quality-ms (ours)        | The whole phase-0 foundation, CAPA workflow, audits, document control                             | QMS-only modules (certificates, gauges)  |

## Stack: latest Laravel + React starter kit

- `laravel new hse --react`: **Laravel 13.34**, the official React starter kit (Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui on Radix, Wayfinder typed routes), Fortify (login, 2FA, passkeys, email verification).
- Added on top: Spatie permission, Pest, Pint, Larastan level 7. ESLint, Prettier and `tsc` come with the kit.
- CI command `composer ci:check` = lint + `types:check` + phpstan + tests, the same shape as hrms.
- **React UI ported from `~/Sites/hrms`** (same kit, already React): `data-table.tsx` + `TableQuery` (server-side sort/filter/paginate), `form-dialog.tsx`, `confirm-dialog.tsx`, `detail-page.tsx`, `document-input.tsx`, `action-menu.tsx`, `import-export.tsx`, `dashboard-widgets.tsx`.
- **Backend ported from `~/Sites/quality-ms`** (framework-agnostic PHP): `Auditable`, `HasCreator`, `StoresUploads`, `HasSignatures`; `Sequence`, `Csv`, `Notify`; `RolesSeeder`/`userWithRole()`; `transitionTo()` + `ALLOWED` map. Its Livewire `WithTable`/`SignsRecords` are replaced by hrms `TableQuery` + a React password re-entry dialog.
- `CLAUDE.md` rules merged from hrms (React side) and quality-ms (domain side); `deploy-assets.yml` + `tests.yml` + `scripts/deploy.sh` copied from hrms.
- MySQL locally (MAMP, DB `hse`), in-memory SQLite for tests.
- Staging: `~/hse` → `https://ui.staging.oriclabdev.com/hse`, DB `stagingoriclabde_hse`, built in Actions with `APP_PATH_PREFIX=hse` (no node on the server).

## Shared foundations (phase 0)

- **Roles:** `admin`, `hse-manager`, `supervisor`, `permit-issuer`, `worker` (report + own actions only), `auditor` (read-only). Permissions `manage|create|edit|delete|approve-x` per module.
- **Sites and areas:** `sites` → `areas` (zone/location). Every record carries `site_id` and users get a site scope. This is the one multi-site feature; it is not multi-tenancy.
- **People:** `users` plus `contractors` (company, insurance expiry, approved flag). Contractor workers are users with `contractor_id`.
- **Numbering:** `INC-2026-0001`, `OBS-…`, `PTW-…`, `ACT-…` from `Sequence`.
- **Photos:** incidents/observations/inspection items need several photos, so this is the one place we add a `photos` morph table (path, sha256, caption). Everything else keeps the single-file `StoresUploads` pattern.

## Modules

### 1. Reporting: observations and near misses

- `observations`: type `unsafe-act | unsafe-condition | near-miss | positive | environmental`, site/area, description, photos, reporter (nullable when `anonymous`), immediate action taken, severity potential `low | medium | high`.
- Status `open → actioned → closed`. A high-potential near miss offers "escalate to incident" with fields pre-filled.
- Mobile-first Inertia page: one screen, camera input (`<input type="file" accept="image/*" capture>`), GPS from the browser geolocation API, submitted with Inertia `useForm` (upload progress built in). No native app.

### 2. Incidents and investigations

- `incidents`: type `injury | illness | property-damage | environmental-release | vehicle | fire | security`, occurred_at, site/area, people involved (`incident_people`: user or free-text name, role `injured | witness | involved`, body part, nature of injury, treatment `first-aid | medical | restricted | lost-time | fatality`, days lost/restricted).
- Classification is derived from the worst treatment (first aid → recordable → LTI → fatality), not entered by hand.
- **Investigation:** team, sequence of events, 5-Why text, root-cause category (seeded list: procedures, training, equipment, supervision, environment, human factors), contributing factors.
- Status `reported → under-investigation → actions-in-progress → closed`. An incident cannot close while an action is open; the investigation is required for recordable-and-above.
- **OSHA 300/300A/301 log** generated per site and year as a printable view (browser print-to-PDF, as in quality-ms). Configurable by region later; RIDDOR/DOSH forms only if a user asks.

### 3. Corrective actions (one register for everything)

- `actions`: number, source morph (observation, incident, inspection item, audit finding, risk, permit), description, owner, due, priority, hierarchy-of-controls level `elimination | substitution | engineering | administrative | ppe`.
- Status `open → done → verified` (or `rejected` back to open). **Verifier must not be the owner.**
- This replaces quality-ms's 8D CAPA. The HSE equivalent is investigation + actions.

### 4. Risk assessments (HIRA / JSA)

- `risk_assessments`: title, site/area, activity, type `hira | jsa | coshh | manual-handling`, review date, status `draft → approved → superseded` (revisions like quality-ms documents).
- `hazards`: hazard, who is affected, existing controls, likelihood × severity (5×5) = initial risk; extra controls; residual risk. A residual score of 15 or more blocks approval.
- An incident linked to a risk assessment flags it "review required". This is the loop back.
- Settings hold the matrix labels and colours (one JSON setting, not a table).

### 5. Inspections and audits

- `checklist_templates` + `checklist_items` (question, response type `yes-no-na | rating | number | text`, failing answer, critical flag).
- `inspections`: template (pinned revision), site/area, inspector, date, `inspection_answers` with photos. Score % computed.
- **Rule:** a failing answer raises a draft action linked to that answer, the SafetyCulture pattern.
- Seed templates: daily site walk, scaffolding, fire extinguisher, PPE, housekeeping, vehicle pre-use.
- ISO 45001 internal audit: port quality-ms `QualityAudit` + `AuditFinding` with the clause list swapped to ISO 45001 §4–§10.

### 6. Permit to work (control of work)

- `permits`: type `hot-work | confined-space | work-at-height | electrical | excavation | lifting | general`, site/area, contractor, work description, valid from/to (max one shift; extensions are logged), linked JSA (required).
- Per-type precaution checklist (seeded), gas-test readings for confined space (O₂ 19.5–23.5%, LEL < 10%, H₂S, CO, with numeric limits checked like quality-ms material limits).
- `permit_isolations` (LOTO): point, method, lock no, isolated by/at, verified by, removed by/at.
- Status `requested → approved → active → suspended → closed | cancelled`. Requester ≠ approver; signatures with password re-entry (`HasSignatures` + React confirm-password dialog).
- **Rules:** cannot approve if the contractor or any listed worker has an expired required competency (module 7); cannot close while any isolation is not removed; two active permits that conflict on the same area are flagged, e.g. hot work and confined space.

### 7. Training and competency

- `competencies` (e.g. "Confined space entry", "Working at height", validity months), `user_competencies` (issued, expires, certificate file).
- Matrix view: people × competencies, coloured valid/expiring/expired.
- `toolbox_talks`: topic, date, presenter, attendees (sign-on), can reference an incident ("lessons learnt").
- Daily command emails expiring competencies (30 days), together with permit expiry and action due reminders: one scheduled `hse:reminders` command, as in quality-ms.

### 8. Documents

Port quality-ms document control unchanged (policies, safe work procedures, emergency plans, read-and-acknowledge).

### 9. Dashboard and KPIs

- Leading: observations per month, inspections completed vs planned, actions overdue, toolbox talks held.
- Lagging: LTIs, recordables, **LTIFR** (LTI × 1,000,000 / hours) and **TRIR** (recordables × 200,000 / hours). Hours worked are entered monthly per site (`site_hours` table).
- Days since last LTI per site, safety pyramid (near misses → first aid → recordable → LTI), heatmap of incidents by area. CSS bars only, no chart library, as in quality-ms.

## Phases (each one shippable with CI green)

| Phase                          | Scope                                                                                                                                                                                                   | Done when                                                                                      |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| 0                              | `laravel new --react` + port the hrms UI pieces and quality-ms backend foundations listed above; roles; sites/areas; contractors; users admin; photos table; CI + deploy workflow; staging DB + symlink | `composer ci:check` green; login + `/users` + `/sites` work locally and on staging             |
| 1                              | Observations/near misses (mobile form, anonymous, photos, GPS) + action register with independent verification                                                                                          | Worker reports a near miss from a phone; the action cannot be verified by its owner            |
| 2                              | Incidents, people involved, derived classification, investigation + 5-Why, close gate, OSHA 300 log print                                                                                               | LTI incident blocks closure until its actions are verified; 300 log totals match the seed data |
| 3                              | Risk assessments (5×5, residual block, revisions, review-required flag from incidents); checklist inspections with auto-actions                                                                         | Failing critical answer raises an action; linked incident flags the JSA for review             |
| 4                              | Permit to work + LOTO + gas tests + signatures; competencies matrix + toolbox talks; reminders command                                                                                                  | Expired confined-space ticket blocks approval; permit with an unremoved lock cannot close      |
| 5                              | Dashboard KPIs (LTIFR/TRIR/days since LTI/pyramid); ISO 45001 audits + documents ported from quality-ms                                                                                                 | KPI figures match hand-calculated seed numbers                                                 |
| 6 (optional, needs a decision) | Environmental: waste/emissions log, spills; chemical/SDS register; AI "describe the incident" auto-fill (Claude API, like quality-ms cert extraction); PWA offline queue                                | —                                                                                              |

## Demo data

Two profiles in `database/demo/*.json`: a construction site (scaffolds, hot work, subcontractors) and a manufacturing plant (LOTO, forklifts, chemicals).

## Out of scope for v1

Native mobile apps (a responsive form plus browser camera/GPS is enough), IoT/wearables, CV PPE detection, ESG/carbon accounting, multi-tenancy, no-code form builder. Add when a real user needs them.
