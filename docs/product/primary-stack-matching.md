# Primary backend stack matching

JobPilot must not treat incidental technology mentions as evidence that an offer is a strong match.

## V1 deterministic guard

`MatchingScoreService` now identifies a dominant backend stack from the offer before allowing the existing score to remain a strong match.

The first V1 slice recognizes these backend families:

- PHP/Symfony (`php`, `symfony`, `laravel`)
- Java/Spring (`java`, `spring`, `spring boot`, `quarkus`)
- Python (`python`, `django`, `fastapi`, `flask`)
- .NET/C# (`.net`, `dotnet`, `c#`, `asp.net`)

Signals are weighted deliberately:

1. technology present in the title: strongest signal;
2. technology present near the beginning of the description: medium signal;
3. technology mentioned elsewhere in the full description: weak contextual signal.

The candidate's preferred backend stack is inferred from configured `targetJobs` and `skills`, with target job titles weighted more strongly than individual skills.

When an offer has a detected primary backend stack and none of those primary alternatives overlaps the candidate's preferred backend stack, the final score is capped at `45/100`. The offer is not hard-rejected: it remains visible and reviewable.

The score reasons expose the detected primary stack and any conflict cap, for example:

```text
Stack principale détectée : Java/Spring
Conflit de stack principale avec le profil : score plafonné à 45/100
```

## Alternatives and secondary technologies

If two stacks receive the same strongest primary signal, both are considered genuine primary alternatives. Therefore an offer such as `Java or PHP` is not penalized when PHP is a preferred stack.

A weaker secondary or contextual mention does not override the dominant stack. A Symfony/PHP role that merely mentions Java as useful integration knowledge remains a PHP/Symfony match.

## Safety boundary

This guard is local and deterministic. It does not change connector queries, authentication, external submissions, CAPTCHA handling, quotas, database schema, or API contracts.

This is the first focused slice of V1 issue #70. Future slices may expand stack families or improve requirement-section parsing, but should preserve deterministic regression fixtures unless the roadmap explicitly adopts an AI-based matcher.
