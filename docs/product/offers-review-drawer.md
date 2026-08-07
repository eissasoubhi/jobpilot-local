# Offers review drawer

The Offers workspace now provides an in-place review drawer for each prepared application.

## Goal

Review the important context of an opportunity without leaving the Offers page.

## Available context

The drawer displays only data already loaded for the offer and its prepared application:

- title, company, location, work mode and contract type;
- matching score and existing score reasons;
- full offer description;
- selected CV and its existing local download link;
- prepared application message;
- requested cover letter when present;
- compensation answer when present;
- the original source-platform link when available.

## Interaction

- `Examiner` opens the drawer without a page navigation;
- `Fermer`, the backdrop, or the `Escape` key closes it;
- the drawer is exposed as an accessible modal dialog;
- opening the drawer performs no API mutation and no external request;
- applying on the external platform remains a deliberate user action through the existing source link.

## Migration boundary

This is an incremental V1 Unified Offers Workspace step. Editing prepared material and application tracking actions remain available through the existing flows until they are moved safely into Offers with equivalent tests. The Applications page must remain available until functional parity is reached.

## Safety

This change does not alter connectors, source policies, authentication, CAPTCHA handling, quotas, scoring, preparation, submission, database schema, or application status transitions.
