# CRM follow-up tasks

JobPilot stores follow-up reminders as local CRM data. A task references an organization already derived by the CRM directory and may optionally reference one of its stable contact keys. It never changes applications, positionings, Gmail headers, offers, organization grouping or contact deduplication.

## Interface

The **Relances CRM** page at `/crm/follow-ups` provides:

- creation of a reminder for an existing CRM organization;
- optional attachment to one of that organization’s existing contacts;
- title, optional note and calendar due date;
- open, completed and all-task views;
- overdue, today, upcoming and completed badges;
- explicit completion and reopening actions.

The interface never sends an email, notification or application automatically. It only reads and writes the local follow-up API. Organization and contact choices come from the current CRM directory, so the interface cannot invent a target.

## Endpoints

```text
GET /api/crm/follow-ups?status=open
GET /api/crm/follow-ups?status=completed
GET /api/crm/follow-ups?status=all
```

Tasks are returned by due date, then identifier. The default filter is `open`.

Create a task:

```text
POST /api/crm/organizations/{organizationKey}/follow-ups
```

```json
{
  "contactKey": "jane@acme.test",
  "title": "Relancer pour la mission Symfony",
  "note": "Demander la date de décision.",
  "dueAt": "2026-08-12"
}
```

`contactKey` and `note` are optional. The organization must exist in the current derived CRM directory. When a contact key is supplied, that contact must belong to the organization. Unknown targets return `404`.

Validation rules:

- title: required, one line, at most 180 characters;
- note: optional, at most 2,000 characters;
- dueAt: required calendar date in `YYYY-MM-DD` format.

Complete or reopen a task:

```text
PATCH /api/crm/follow-ups/{id}
```

```json
{ "completed": true }
```

Repeating completion preserves the original completion timestamp. Sending `false` reopens the task.

## Storage and rollback

Tasks are stored in the additive `crm_follow_up_task` table. The migration is reversible and drops only local reminders. It does not delete or modify source records.

The interface itself has no migration. Rolling it back removes only the page, navigation entry and presentation helpers; existing tasks and source records remain intact.
