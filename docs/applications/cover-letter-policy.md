# Cover letter preparation policy

JobPilot keeps the application email and the cover letter as two separate pieces of content.

A concise application email is always prepared for an eligible application. A cover letter is prepared only when the offer text explicitly requests one.

## Detected requests

The deterministic detector recognizes common French and English wording such as:

- `lettre de motivation`;
- `lettre de candidature`;
- `cover letter`;
- `motivation letter`;
- a request to send a CV or résumé together with a letter.

The detector intentionally remains conservative. A normal job description, a generic request to apply, or a request for a CV alone does not create a cover letter.

## Explicit exclusions

A cover letter is not prepared when the offer says that it is optional, not requested, not required, or that the CV alone is sufficient. French and English negative wording takes precedence over a positive keyword appearing elsewhere in the same offer.

## Stored application content

When no cover letter is requested, the application keeps an empty `coverLetter` value. The email message remains complete and can still be edited or copied independently.

When a letter is requested, JobPilot stores it separately from the email. Automatic Gmail delivery continues to send only the concise email and the selected CV; the cover letter is never concatenated into the email body.

## Applications interface

An empty generated letter is not displayed as a blank textarea. The interface states that the offer did not request a letter and keeps the concise message visible.

The user can deliberately open a manual letter editor. Until text is entered, the copy action remains disabled and the interface labels the content as a manual addition rather than an offer requirement.

A prepared or manually entered letter remains editable, copyable, and stored separately from the email message.

## Limits

The decision is based on explicit text patterns, not an external AI model. Ambiguous wording may require manual correction from the Applications page. The detector does not invent qualifications, experience, salary, or availability.
