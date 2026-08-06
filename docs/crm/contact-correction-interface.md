# CRM contact correction interface

The **Contacts CRM** page at `/crm/contacts` lists contacts already derived from applications, positionings and linked Gmail messages.

For each contact, the interface can save a local correction for the displayed name, email address or phone number through:

```text
PUT /api/crm/organizations/{organizationKey}/contacts/{contactKey}/correction
```

The editor always shows the immutable contact key and the original source values returned by the CRM directory. Corrections are overlays only: Gmail headers, positioning recruiter data, application addresses, deduplication and stable contact identities are not rewritten.

Submitting all three fields empty removes the local correction. The directory is reloaded after every successful save so server-side validation, source fallback and correction counts remain authoritative.

The page also provides local, client-side controls to:

- search effective and original source names, email addresses, phone numbers and organization names;
- display all contacts, only locally corrected contacts, or only contacts without a correction;
- show total, corrected and currently visible contact counts;
- display a dedicated empty state when no contact matches the active filters.

Search is case-insensitive and accent-insensitive. Filtering does not modify CRM data and does not trigger any external request beyond the existing directory load.

The backend validates names, email addresses and phone values. Errors remain in the editor and do not alter the visible contact data.
