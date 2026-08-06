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

## UX direction

The Offers page is the target single workspace for reviewing an offer, its matching explanation and prepared application material, then deciding whether to apply, ignore or archive it. The separate Applications page can be retired only after those controls are available and covered by tests.

## Safety

This workflow does not change connector access, authentication, quotas, CAPTCHA handling or source-compliance rules. It does not add any external submission request.
