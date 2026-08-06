# Offer preparation workflow

JobPilot prepares every valid offer automatically so the user can decide what to do from the offer itself instead of waiting for a matching threshold.

## Product rules

- A valid offer is prepared after language detection, matching, compensation checks and CV selection.
- The matching score remains visible as decision support, but it no longer gates preparation.
- Hard filters still prevent preparation when an offer is explicitly excluded or violates configured compensation eligibility rules.
- Preparation creates or refreshes the local application draft with the selected CV, concise message, compensation answer and cover letter only when required.
- Preparing an offer never submits it.
- The background scheduler never submits prepared applications.
- Final submission remains a deliberate user action. Existing automatic-submission implementation is retained only for backward compatibility and is not invoked by the normal offer-processing or scheduling flow.

## Offers workspace

The Offers page is the primary workspace for reviewing opportunities. For every application draft already associated with an offer, the offer card now shows:

- the current application tracking status;
- whether the CV, application message, requested cover letter and compensation answer are ready;
- the selected CV with a direct local preview/download link;
- the prepared message, requested cover letter and compensation answer inside an expandable review section;
- a direct link to the original platform when a source URL is available.

Loading application preparation data is intentionally non-blocking: if that API call fails, the offers themselves remain available and the page reports that only the preparation details are unavailable.

This is an incremental step toward the single-workspace UX. Editing the prepared material and updating submission status still remain on the Applications page until those interactions are safely moved into Offers and covered by tests.

## UX direction

The target is to review an offer, its matching explanation and prepared application material, then decide whether to apply, ignore or archive it without navigating to another page. The separate Applications page can be retired only after all of its editing and tracking controls are available in Offers and covered by tests.

## Safety

This workflow does not change connector access, authentication, quotas, CAPTCHA handling or source-compliance rules. It does not add any external submission request.
