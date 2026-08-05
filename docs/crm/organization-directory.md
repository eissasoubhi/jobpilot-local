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

The response contains global organization/contact counts and one entry per organization with:

- organization roles;
- offer, application, positioning and linked-message counts;
- application and positioning status distributions;
- last known activity;
- detected contacts and their origins;
- up to five recent related offers;
- the original generated `sourceName`;
- an optional manual `annotation` containing a display-name correction, note and update date.

A display-name correction changes only the returned `name`. The stable organization `key` and `sourceName` remain unchanged.

## Manual annotation endpoint

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

## Interface

The **CRM** entry in the main navigation opens `/crm`.

The page provides:

- global organization, validated-contact and visible-result counts;
- accent-insensitive search across organization names, contacts, emails, phone numbers and recent offer titles;
- filtering by company, intermediary or final-client role;
- contact origin badges;
- direct `mailto:` and `tel:` actions for validated data;
- application and positioning status summaries;
- links back to recent original offers when their URL is available.

The current interface displays the generated directory. Editing the new annotations from the CRM page is a separate UI delivery.

## Contact rules

Contacts are merged by validated lowercase email address. A positioning recruiter and a linked Gmail sender using the same address become one contact with both roles.

An application address remains labeled as an application address. A Gmail header without a valid email address does not create a contact. JobPilot does not infer a person from a company domain, message text or display name alone.

## Privacy

The directory exposes no Gmail body, snippet, OAuth token, CV content or cover letter. Manual notes are local application data and are returned only through the local CRM endpoint.

## Current limits

Manual contact corrections, organization merge overrides and follow-up tasks remain separate roadmap deliveries. Because the directory is generated from current records, correcting an offer, positioning or linked message automatically changes the next response while annotations stay attached to the stable organization key.
