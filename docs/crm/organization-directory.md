# CRM organization directory

The CRM directory consolidates organizations and contacts already present in JobPilot. It does not scrape public profiles, query an external enrichment service, or invent recruiter identities.

## Data sources

The directory combines:

- application companies, final clients, application addresses and outcomes;
- positioning agencies, final clients, recruiter names, emails and phone numbers;
- Gmail senders or reply-to addresses only when the message is already linked to a JobPilot offer.

Organizations with the same normalized name are merged. Their roles remain explicit: company, agency or final client.

## Directory endpoint

```text
GET /api/crm/organizations
```

The response contains global organization, contact, annotation and contact-correction counts. Each organization includes:

- organization roles;
- offer, application, positioning and linked-message counts;
- application and positioning status distributions;
- last known activity;
- detected contacts and their origins;
- up to five recent related offers;
- the original generated `sourceName`;
- an optional manual `annotation` containing a display-name correction, note and update date.

A display-name correction changes only the returned `name`. The stable organization `key` and `sourceName` remain unchanged.

Each contact exposes its stable `key`, its effective `name`, `email` and `phone`, and the original values as:

- `sourceName`;
- `sourceEmail`;
- `sourcePhone`.

An optional `correction` contains `correctedName`, `correctedEmail`, `correctedPhone` and `updatedAt`. A null correction field falls back to the corresponding source value. The stable contact key never changes, even when the displayed email is corrected.

## Manual organization annotation endpoint

```text
PUT /api/crm/organizations/{organizationKey}/annotation
```

The organization key must identify an organization currently derived from JobPilot data. URL-encode spaces and non-ASCII characters when calling the endpoint.

Example payload:

```json
{
  "displayName": "ACME Consulting France",
  "note": "Contact prioritaire pour les missions Symfony. Relancer dans une semaine."
}
```

Both fields are optional. Empty values clear the corresponding field. Sending both fields empty removes an existing annotation instead of keeping an empty database row.

The display name is limited to one line and 255 characters. The note is limited to 5,000 characters. Unknown organization keys return `404`; invalid annotation content returns `422`.

Annotations are stored in `crm_organization_annotation`, separately from offers, applications, positionings and Gmail metadata. They never rewrite source records or alter organization grouping.

## Manual contact correction endpoint

```text
PUT /api/crm/organizations/{organizationKey}/contacts/{contactKey}/correction
```

Both path values must identify a contact currently derived inside that exact organization. URL-encode the stable keys, including spaces and `@` characters.

Example payload:

```json
{
  "name": "Jane Recruiter France",
  "email": "jane.france@acme.test",
  "phone": "+33 6 10 20 30 40"
}
```

All fields are optional. Empty values clear the corresponding correction. Sending all three fields empty removes an existing correction instead of keeping an empty row.

Validation rules:

- name: one line, at most 255 characters;
- email: a valid address, normalized to lowercase, at most 254 characters;
- phone: one line, at most 64 characters, containing at least one digit.

An unknown organization/contact pair returns `404`. Invalid correction content returns `422`.

Corrections are stored in `crm_contact_correction`, keyed by the immutable organization and contact keys. They do not update the recruiter data in a positioning, the sender or reply-to of a Gmail message, or an application address. Stale corrections are ignored when the derived contact no longer exists.

## Interface

The **CRM** entry in the main navigation opens `/crm`. The dedicated **Contacts CRM** entry opens `/crm/contacts`.

The organization page provides:

- global organization, validated-contact, annotated-record and visible-result counts;
- accent-insensitive search across corrected names, original source names, notes, contacts, emails, phone numbers and recent offer titles;
- filtering by company, intermediary or final-client role;
- contact origin badges;
- direct `mailto:` and `tel:` actions for validated data;
- application and positioning status summaries;
- links back to recent original offers when their URL is available;
- a local editor for the organization display-name correction and internal note;
- an explicit clear action that removes only the organization annotation after confirmation.

The contact page provides:

- accent-insensitive search across displayed and source names, emails, phones and organization names;
- filters for all, locally corrected or uncorrected contacts;
- editing and clearing of local contact corrections;
- counters for total, corrected and currently visible contacts;
- CSV export limited to the currently visible filtered contacts.

The CSV contains the organization, displayed contact values, roles, correction status, original source values, last-contact date and linked-message count. It uses UTF-8 with a byte-order mark and semicolon separators for common French spreadsheet applications. Every cell is quoted, and values starting with `=`, `+`, `-` or `@` are prefixed with an apostrophe to prevent spreadsheet formula execution. Exporting never changes CRM or source records.

A corrected organization card displays both the CRM name and the original source name. Saving or clearing re-fetches the complete server directory so annotation counts, sorting and overlays remain authoritative.

The organization and contact editors show immutable keys and original source values before saving. Errors remain inside the modal, and a failed save does not close the editor or modify the visible directory.

## Contact rules

Contacts are merged by validated lowercase source email address. A positioning recruiter and a linked Gmail sender using the same source address become one contact with both roles.

An application address remains labeled as an application address. A Gmail header without a valid email address does not create a contact. JobPilot does not infer a person from a company domain, message text or display name alone.

A corrected displayed email does not change contact deduplication or the stable key. This prevents a manual correction from silently splitting or merging source records.

## Privacy

The directory exposes no Gmail body, snippet, OAuth token, CV content or cover letter. Manual notes and corrections are local application data and are returned only through the local CRM endpoint.

The CSV export contains only fields already visible in the local CRM contact directory. It is generated entirely in the browser and is not uploaded to an external service.

## Rollback

The CSV export has no migration and can be rolled back by removing the frontend action and helper. Existing organization annotations, contact corrections and source records remain unchanged.

The contact-correction migration remains reversible and drops only `crm_contact_correction`. Rolling it back removes local contact overlays but leaves every application, positioning, offer and Gmail-derived source value unchanged.

## Current limits

Organization merge overrides, follow-up tasks and a full chronological application cycle remain separate roadmap deliveries. Because the directory is generated from current records, correcting an offer, positioning or linked message automatically changes the next response while local annotations and corrections stay attached to stable derived keys.
