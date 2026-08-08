# Primary backend stack matching

JobPilot must not treat incidental technology mentions as evidence that an offer is a strong match.

## V1 deterministic guard

`MatchingScoreService` identifies a dominant backend stack from the offer before allowing the existing deterministic score to remain a strong match.

The V1 guard recognizes these backend families:

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

## Discriminating title compatibility

The deterministic fallback no longer searches target-job title words across the full offer description when calculating `Compatibilité intitulé`.

Title compatibility is now calculated from the actual offer title only. This prevents a technology mentioned in narrative, legacy or contextual text from pretending that the offer title matches a configured target role.

Target-title tokens are split into two groups:

- **specific/discriminating tokens**, such as `PHP`, `Symfony`, `React`, `Vue`, `Java` or another technology/product term;
- **generic role tokens**, such as `developer`, `backend`, `frontend`, `web`, `API`, `engineer`, `senior`, `lead` or `full stack`.

Specific tokens can contribute up to `30/35` points and generic title context up to `5/35`. If a configured target title contains only generic role words, its title compatibility is capped at `10/35` even when those generic words all match.

This means a title such as `Senior Web Backend Developer` cannot become a strong match merely because the configured target is `Senior Backend Developer`. A genuinely discriminating title such as `Senior PHP Symfony Developer` or `React Developer` can still receive the full `35/35` title score.

The skills score remains separate: a generic title can still become a good match when the offer contains enough genuine configured skills and no contradictory primary-stack signal.

## Alternatives and secondary technologies

If two stacks receive the same strongest primary signal, both are considered genuine primary alternatives. Therefore an offer such as `Java or PHP` is not penalized when PHP is a preferred stack.

A weaker secondary or contextual mention does not override the dominant stack. A Symfony/PHP role that merely mentions Java as useful integration knowledge remains a PHP/Symfony match.

## AI path

When the opt-in AI matcher returns a valid structured analysis, that semantic score is used instead of the deterministic fallback. The AI prompt has the same product rule: generic backend/web/API/developer words must not be enough for a high score, and the primary role/stack must be identified first.

If AI is disabled, unavailable, quota-limited or returns an invalid response, the deterministic rules documented above remain the fallback.

## Safety boundary

These guards are local matching behavior only. They do not change connector queries, authentication, external submissions, CAPTCHA handling, source quotas, database schema, or API contracts.

Issue #70 remains covered by deterministic regression fixtures even while the optional AI matcher evolves.
