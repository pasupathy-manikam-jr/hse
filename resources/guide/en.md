# User guide

HSE keeps your health, safety and environment records in one place and links them together: an observation can become an incident, an incident is investigated, the investigation raises corrective actions, and the risk assessment that should have prevented it is flagged for review. Nothing is closed until someone other than the person who did the fix has checked it.

[TOC]

## Getting started

### Signing in and your account

Sign in with the email and password your administrator gave you. Under **Settings** (in your user menu, bottom of the sidebar) you can change your name, email and password, and turn on two-factor authentication or a passkey.

### Finding your way around

The sidebar groups the work:

- **Platform**: the dashboard and the weekly report.
- **Report**: report an observation, observations, incidents and the actions list.
- **Control**: risk assessments, inspections, audits, gap analysis, documents, permits to work, shift handovers and the equipment register.
- **Environment**: the environmental log and the chemical register.
- **People**: the training matrix and toolbox talks.
- **Setup**: sites, contractors, checklists and users.

You only see the pages your role allows. If you open a link you cannot use, you get a page saying so, with a way back to the dashboard.

### Sites

Everything belongs to a site (a project, plant or depot). If your account has a home site, you see and record things for that site only. HSE managers and administrators usually have no home site and see every site.

### Lists

Every list works the same way: type in the search box, use the drop-downs and status tabs to narrow it down, click a column heading to sort, and choose how many rows to show. The address bar keeps your filters, so you can bookmark or share a filtered list.

### Forms

A red **\*** after a field name means it must be filled in. When something is missing or wrong, the message appears under the field when you save. Dates are picked from a calendar; times are entered next to the date.

### Signing with your password

Some steps are signed: approving or closing a permit, approving a document revision. You re-enter your password, and the system records who signed, what it meant and when. A wrong password changes nothing.

## Roles

| Role          | What they do                                                                                                                                                                                                                 |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin         | Everything, including users.                                                                                                                                                                                                 |
| HSE manager   | Runs the system: approves risk assessments and documents, verifies actions, enters hours worked, chooses the observation of the month, manages sites, contractors, checklists and equipment.                                 |
| Supervisor    | Reports and investigates incidents, reviews and dismisses observations, raises and verifies actions, writes risk assessments, runs inspections, requests permits, records training, toolbox talks and equipment inspections. |
| Permit issuer | Approves (signs) permits to work, reports observations and incidents, runs inspections.                                                                                                                                      |
| Worker        | Reports observations, completes the actions given to them, reads documents they are asked to read, and can open the chemical register.                                                                                       |
| Auditor       | Reads everything and conducts internal audits.                                                                                                                                                                               |

## Dashboard

The dashboard shows, for the last 12 months or the year to date and for one site or all:

- **Needs you**: actions given to you, actions waiting for your verification, permits to approve, assessments to approve or review, documents to read or review, shift handovers to acknowledge, incidents still to report to DOSH. Click a card to go straight there.
- **Injuries**: LTIFR (lost-time injuries per million hours), TRIR (recordable injuries per 200,000 hours), the counts behind them, hours worked, and days since the last lost-time injury at each site. The rates need monthly hours worked (see below).
- **Safety pyramid**: near misses, first aid, medical treatment, restricted work, lost time and fatalities in the period.
- **Observations reported** per month, and **Prevention**: inspections completed and their average score, toolbox talks, open and overdue actions.
- **Observation of the month** at each site, **what observations are about** (the most common topics) and the **top reporters** (named reports only; anonymous and dismissed ones are not counted).

**Hours worked** (HSE managers): click **Hours worked**, choose the site and month, and enter the total hours worked by staff and contractors and the average number of workers (for the JKKP 8 rates). Saving the same month again corrects it.

## Reporting an observation

Use **Report** in the sidebar. It is built for a phone:

1. Tap what you saw: unsafe condition, unsafe act, near miss, environmental, or good practice.
2. Choose the area, check the date and time, pick what it is about (PPE, housekeeping, lifting…) if you know, and describe it. Say what you did about it if anything.
3. Choose how bad it could have been, add up to five photos (the camera or your gallery) and, if you like, **Add my location**.
4. Tick **Report anonymously** if you prefer: your name is then not stored with the report or in its history.
5. **Send report**.

**No signal?** The form still opens if you have used it on that phone before. Your report, photos included, is saved on the phone and sent automatically when the signal comes back; a yellow banner shows how many are waiting. Do not clear the browser's data while reports are waiting.

### Following up observations

Supervisors and managers see all reports under **Observations**. Open one to see the photos and the map link, then:

- **Review** to set the topic and the crew (contractor) involved. These feed the dashboard trends.
- **Raise action** for each fix needed. The observation becomes _actioned_.
- **Escalate to incident** if it turned out to be an incident: the incident form opens with the details filled in.
- **Close** it once every action is verified (or straight away if nothing more is needed).
- **Dismiss** a report that is not valid, such as a duplicate or something that is not a hazard, and say why. Only a report with no actions can be dismissed. It is kept, but takes no actions and is left out of every count.

**Observation of the month** (HSE managers): open a good report from a named person and click **Observation of the month**, saying why it stood out. Each site has one per month, for the month the observation was made. It shows on everyone's dashboard with the reporter's name.

## Incidents

Report an incident from **Incidents → Report incident**: type, when, where, a short title, what happened and the immediate actions taken, with photos. If drafting is switched on, you can first describe it in your own words and click **Draft the report** to fill in the form; check every field before you report. Only that text and the site's area names are sent to the drafting service, so leave out people's names.

On the incident page:

- **People involved**: add everyone injured, plus witnesses. For each injured person record the treatment (first aid, medical treatment, restricted work, lost time, fatality), the nature of the injury, the body part and days lost or restricted. For an occupational illness, choose the type of illness. Tick **Privacy case** to keep the name off the injury log. The incident's **classification** follows the worst treatment given; medical treatment or worse makes it **recordable**.
- **Investigation**: the team, the sequence of events, up to five whys, the root cause and its category, and contributing factors. Saving it starts the investigation.
- **Corrective actions**: raise one for each fix the investigation calls for.
- **Edit**: correct the details and link the **risk assessment that covered the work**. Linking it flags that assessment for review.

An incident moves from _reported_ to _under investigation_ to _actions in progress_ (**Investigation done**, which needs the root cause and at least one action) to _closed_. It closes only when every action is verified, and a recordable incident must also have been investigated. A closed incident cannot be changed.

### Telling DOSH (Malaysia, NADOPOD 2004)

Under the Occupational Safety and Health Act 1994 (section 32) and the NADOPOD Regulations 2004, some incidents must be reported to DOSH (JKKP). The incident page works out which, from what you record, and shows a yellow **Notifiable to DOSH** panel:

| What happened                                                                                                                                                                                                                                                                  | What to do                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| A death, a **serious bodily injury** (tick it on the injured person: a fracture, an amputation, the loss of sight… as listed in the First Schedule), or a **dangerous occurrence** (choose its code when you edit the incident, e.g. 110 scaffold collapse, 610 crane failure) | Tell the nearest DOSH office **at once** by phone or email, then send **JKKP 6** within 7 working days |
| An injury keeping someone off work for **more than 4 days**                                                                                                                                                                                                                    | Send **JKKP 6** within 7 days                                                                          |
| An **occupational poisoning or disease** (an illness incident)                                                                                                                                                                                                                 | Send **JKKP 7** within 7 days                                                                          |

Click **Record DOSH notice** after the phone call (when, and who you spoke to), and **JKKP 6 submitted** once the form is filed on DOSH's online system, with its reference. The due date shown is 7 calendar days, the safe side of 7 working days. Until the form is recorded, the incident appears under **Needs you** on the dashboard and in the daily email of everyone who can edit incidents at that site, and it cannot be closed. This applies to contractors' workers on your site too: the employer in overall control of the workplace reports, and the contractor reports as well.

### JKKP 8 register

**Incidents → JKKP 8 register** lists, for a site and year, the accidents (medical treatment or worse), dangerous occurrences and occupational poisonings or diseases, with the annual summary: cases, deaths, days lost (a death counts 6,000), man-hours and average workers, and the incident, fatality, frequency and severity rates. The man-hours and average workers come from the monthly figures entered under **Hours worked** on the dashboard. Send the summary to DOSH HQ by **31 January** and keep the register for 5 years. If there were no cases, tell DOSH rather than sending a blank form. **Print** gives a clean copy.

### Injury log

**Incidents → Injury log** shows the US OSHA 300 log and 300A summary for a site and year: one line per recordable case, with the outcome (G–J), days away and restricted (counted up to 180), and the injury or type of illness. Privacy cases show as "Privacy case". **Print** gives a clean copy.

## Actions

Every corrective action, wherever it was raised (observations, incidents, inspections, audits), is in **Actions**. Without the manager role you see only your own.

1. The owner does the work and clicks **Mark done**, saying what was done.
2. Someone else with the right to verify checks it and clicks **Verify**, or **Send back** with a reason (it returns to the owner).

You can never verify your own action. Overdue actions are flagged in red, and you get a reminder email each morning for actions due or overdue.

## Risk assessments

**Risk assessments → New assessment**: the type (HIRA, job safety analysis, COSHH, manual handling), title, site and area, the activity and a review date. Then add each hazard:

- who is at risk and the controls already in place;
- likelihood and severity (1–5 each) **before** the additional controls;
- the additional controls (elimination and engineering first, PPE last);
- likelihood and severity **after** them.

Scores are likelihood × severity: 1–4 low, 5–9 medium, 10–14 high, 15–25 extreme. The matrix shows where the hazards sit before and after controls.

An HSE manager **Approves** it. The author cannot approve their own, and no hazard may be left at 15 or more after controls. To change an approved assessment, click **New revision**: a draft copy is made, and approving it replaces the old one. When an incident is linked to an assessment it is flagged **Review required**; approve a new revision, or click **Reviewed, no change needed**.

## Inspections and checklists

**Checklists** (Setup) are the templates: questions answered yes / no / N/A, a 1–5 rating (passing at 3 or a set minimum), a number with an acceptable range, or free text. Mark a question **critical** if failing it is serious.

**Inspections → Start inspection**: choose the checklist and the area. Answer each question; a failed answer asks what is wrong and lets you add photos. **Save progress** at any time, and **Complete inspection** when done. On completion the score is worked out (N/A and text answers don't count) and **every failed answer raises an action** for you: critical ones are high priority and due the next day, others due in a week. Editing a checklist later never changes inspections already done.

## Permits to work

**Permits to work → Request permit**: the type of work, site and area, the approved risk assessment or JSA it is done under, the start and end (one shift, at most 12 hours), the work, the contractor if any, and the workers.

On the permit:

- **Precautions**: tick each one when it is in place and **Save precautions**.
- **Gas tests** (confined space): record oxygen, LEL, H₂S and CO. A test passes only with oxygen at 19.5–23.5 %, LEL below 10 %, H₂S below 10 ppm and CO below 25 ppm. A failed test means: do not enter.
- **Isolations**: record each lockout point, method and lock number; **Remove** each one when it is taken off.

A permit issuer (not the person who requested it) **Approves** it with their password. Approval is refused while any precaution is unticked, the contractor is not approved or insured, a worker lacks a valid ticket for this type of work up to the permit's end, the risk assessment is no longer the approved revision, or (confined space) there is no passing gas test. The page lists what is missing.

If the work uses equipment from the register, list it under **Equipment used**. Approval is also refused while any of it is out of service or not inspected through the end of the permit.

Then **Start work** (only within the permit's times; confined-space entry also needs a passing gas test from the last 2 hours), **Suspend** with a reason and **Resume**, and finally **Close** with your password once every isolation is removed. A request can be **Cancelled** with a reason before work starts. Hot work and confined-space permits in the same area at overlapping times are flagged on both permits.

### Shift handovers

At the end of a shift, **Shift handovers → Hand over**: choose the person taking over and write down hazards, ongoing work and anything unusual. The open permits at the site and the locks still on are attached automatically. The person taking over clicks **Acknowledge** once they have read it.

## Training and toolbox talks

The **Training matrix** shows each person against each competency: green valid, amber expiring within 30 days, red expired. Click a cell (or **Record training**) to record a course or ticket with its certificate; the expiry date follows from the competency's validity unless you set it. A competency tied to a permit type is required of every worker on permits of that type. People get a reminder email 30 days before a ticket expires.

**Toolbox talks → Record talk**: the topic, date, who gave it, the key points, everyone who attended and, optionally, the incident whose lessons were shared.

## Internal audits

**Audits → Plan audit**: the site, title, lead auditor, date and the ISO 45001 clauses in scope. **Start audit**, then **Add finding** for each: a major or minor nonconformity, an observation, or an opportunity for improvement. A nonconformity needs an action owner and due date, and raises a corrective action. **Complete** the audit with a summary.

## Gap analysis

A gap analysis checks how close a site is to ISO 45001, clause by clause. Run one before certification and then once a year to show progress. An internal audit is different: it checks that you follow your own system, for the clauses you choose.

**Gap analysis → Start gap analysis**: the site, a title and the date. All 29 clauses are listed. For each one choose **Conforms** (in place, with evidence), **Partly**, **Gap** (not done) or **N/A**, and write the evidence you saw or what is missing. **Save ratings** as you go.

The score for each section (§4 to §10) and overall counts conforms as 100 %, partly as 50 % and gap as 0 %; N/A is left out. A grey mark shows the site's last completed gap analysis, so you can see the change. **Raise action** for each gap to close it. **Complete** it with a summary once every clause is rated and every gap or partial rating says what is missing; it is then read-only.

## Equipment register

**Equipment register → Add equipment**: the asset tag, type (lifting, pressure system, fire, scaffold or access, electrical, vehicle or mobile plant, other), description, serial number, site and area, the person responsible, and how often it must be inspected.

Click the clipboard on a row to see its inspections and **Record inspection**: the date, passed or failed, who inspected it (a competent person or an inspection body), defects found, and the certificate. The latest inspection sets the next due date. A failure takes the equipment out of service, and a later pass puts it back. The list flags equipment that is overdue or due within 30 days, and can be filtered to show just those.

Equipment named on a permit cannot be deleted; mark it out of service instead.

## Weekly report

**Weekly report** shows one week, Monday to Sunday, for one site or all your sites: observations by type and topic, incidents, inspections and their average score, toolbox talks and attendance, permits approved, actions raised, verified, open and overdue (with the overdue ones listed), equipment overdue for inspection, and days since the last lost-time injury. Use the arrows to move between weeks, and **Print** for the weekly meeting.

To have it emailed, edit the site under **Sites** and add the addresses under **Weekly report email**. Every Monday at 07:30 they get last week's figures with a link to the full report. The email carries counts only; the details stay behind the sign-in.

## Documents

**Documents → New document**: the number, title, type, owner, review interval and ISO 45001 clauses. Revision A starts as a draft: upload the file, say what changed, and **Send for approval**. Someone other than the author **Approves** it with their password, and it comes into force, replacing the previous revision.

On an effective revision, **Ask people to read** it. They find it under **My reading** (and on their dashboard), open it, and click **I have read it**; the document page shows who has and hasn't. When the review date comes, either start a **New revision** or click **Reviewed, no change** to move the next review on.

## Environment

**Environmental log**: for each site and month, record general, hazardous and recycled waste, water, electricity and diesel by clicking the cell. The page shows totals for the year, the recycling rate, and spills (incidents of type environmental release).

**Chemical register**: each hazardous substance, its supplier, hazard pictograms, where it is stored and the maximum quantity, its safety data sheet (with issue date) and the COSHH assessment covering its use. A chemical with a health hazard and no approved COSHH assessment shows **Assessment needed**; a safety data sheet over five years old (or missing) is flagged too. Everyone can open the safety data sheets.

## Setup (administrators and HSE managers)

- **Sites**: add sites and their areas (click the map-pin icon on a site to manage its areas), and who gets the site's weekly report email.
- **Contractors**: the company, safety contact and insurance expiry. An HSE manager approves a contractor; only approved, insured contractors can work under permits.
- **Checklists**: inspection templates (see above).
- **Users**: each person's role, home site (blank for all sites) and employer (blank for own staff).

## Emails

Each morning at 07:00 everyone gets one email listing what needs them: actions due or overdue, their competencies expiring within 30 days, permits they requested that are past their end time but still open, and equipment they are responsible for whose inspection is due within 30 days or overdue. Nothing to report means no email.

Every Monday at 07:30, each site's weekly report goes to the addresses on its list (see **Weekly report**).
