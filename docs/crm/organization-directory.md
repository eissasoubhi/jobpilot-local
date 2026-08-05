# CRM organization directory

The CRM directory consolidates organizations and contacts already present in JobPilot. It does not scrape public profiles, query an external enrichment service, or invent recruiter identities.

## Data sources

The read-only directory combines:

- application companies, final clients, application addresses and outcomes;
- positioning agencies, final clients, recruiter names, emails and phone numbers;
- Gmail senders or reply-to addresses only when the message is already linked to a JobPilot offer.

Organizations with the same normalized name are merged. Their roles remain explicit: company, agency or final client.

## Endpoint

```text
GET /api/crm/organizations
```

The response contains global organization/contact counts and one entry per organization with:

- organization roles;
- offer, application, positioning and linked-message counts;
- application and positioning status distributions;
- last known activity;
- detected contacts and their origins;
- up to five recent related offers.

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

The interface is deliberately read-only. It displays the generated directory without silently changing the underlying application, positioning or Gmail records.

## Contact rules

Contacts are merged by validated lowercase email address. A positioning recruiter and a linked Gmail sender using the same address become one contact with both roles.

An application address remains labeled as an application address. A Gmail header without a valid email address does not create a contact. JobPilot does not infer a person from a company domain, message text or display name alone.

## Privacy

The directory exposes no Gmail body, snippet, OAuth token, CV content or cover letter. It only uses contact metadata and aggregate workflow counts already stored locally.

## Current limits

The current directory is derived and read-only. Notes, manual contact editing, organization merging overrides and follow-up tasks are separate roadmap deliveries. Because the directory is generated from current records, correcting an offer, positioning or linked message automatically changes the next response.
