# Primary backend stack matching

JobPilot must not treat incidental technology mentions as evidence that an offer is a strong match.

## V1 deterministic guard

`MatchingScoreService` identifies a dominant backend stack from the offer before allowing the existing deterministic score to remain a strong match.

The V1 fallback currently recognizes these backend families:

- PHP/Symfony (`php`, `symfony`, `laravel`)
- Java/Spring (`java`, `spring`, `spring boot`, `quarkus`)
- Python (`python`, `django`, `fastapi`, `flask`)
- .NET/C# (`.net`, `dotnet`, `c#`, `asp.net`)
- Node.js/NestJS
- Ruby/Rails
- Go/Golang
- Rust
- Kotlin/Scala
- C/C++

The product rule is broader than this deterministic list: the goal is to determine whether an offer is genuinely a PHP-oriented profile or primarily another profile. The optional AI path handles arbitrary technologies semantically; the deterministic list is only the local fallback.

Signals are weighted deliberately:

1. technology present in the title: strongest signal;
2. technology present near the beginning of the description: medium signal;
3. technology mentioned elsewhere in the full description: weak contextual signal.

The candidate's preferred backend stack is inferred from configured `targetJobs` and `skills`, with target job titles weighted more strongly than individual skills.

When an offer has a detected primary backend stack and none of those primary alternatives overlaps the candidate's preferred backend stack, the final score is capped at `45/100`. The offer is not hard-rejected: it remains visible and reviewable.

A strong explicit configured target title can bypass that backend conflict cap. This prevents a legitimate non-PHP target such as an explicitly configured frontend role from being rejected merely because the description mentions a separate backend stack.

The score reasons expose the detected primary stack and PHP profile classification, for example:

```text
Stack principale détectée : Ruby/Rails
Profil PHP détecté : non-PHP principal
Conflit de stack principale avec le profil : score plafonné à 45/100
```

## Discriminating title compatibility

The deterministic fallback no longer searches target-job title words across the full offer description when calculating `Compatibilité intitulé`.

Title compatibility is calculated from the actual offer title only. This prevents a technology mentioned in narrative, legacy or contextual text from pretending that the offer title matches a configured target role.

Target-title tokens are split into two groups:

- **specific/discriminating tokens**, such as `PHP`, `Symfony`, `React`, `Vue`, `Java` or another technology/product term;
- **generic role tokens**, such as `developer`, `backend`, `frontend`, `web`, `API`, `engineer`, `senior`, `lead` or `full stack`.

Specific tokens can contribute up to `30/35` points and generic title context up to `5/35`. If a configured target title contains only generic role words, its title compatibility is capped at `10/35` even when those generic words all match.

This means a title such as `Senior Web Backend Developer` cannot become a strong match merely because the configured target is `Senior Backend Developer`. A genuinely discriminating title such as `Senior PHP Symfony Developer` or `React Developer` can still receive the full `35/35` title score.

The skills score remains separate: a generic title can still become a good match when the offer contains enough genuine configured skills and no contradictory primary-stack signal.

## PHP relevance in the AI path

Gemini returns an explicit `phpRelevance` classification in every valid structured matching analysis:

- `PRIMARY`: PHP is genuinely core to the role;
- `ALTERNATIVE`: PHP is one equivalent primary option, such as `Java OR PHP`;
- `MIXED_REQUIRED`: PHP and another primary backend stack are both mandatory;
- `SECONDARY`: PHP is useful but not core;
- `CONTEXTUAL`: PHP appears only in legacy, migration, integration or incidental context;
- `ABSENT`: PHP is not part of the requested role;
- `UNCLEAR`: there is insufficient evidence.

For a PHP-oriented backend profile, `SECONDARY`, `CONTEXTUAL` and `ABSENT` cannot support an AI score above `45/100` unless the AI-detected role itself strongly matches another explicitly configured `targetJob`. `MIXED_REQUIRED` is capped at `60/100`, because satisfying PHP alone is not sufficient when another backend stack is also mandatory.

This guard is local and deterministic after the AI response. It prevents a contradictory model score from overriding the product rule.

## Alternatives and cumulative requirements

The deterministic fallback now distinguishes common explicit alternatives from clearly cumulative mandatory requirements when PHP and another backend family appear in the same requirement statement.

Alternative cues include forms such as `PHP or Python`, `PHP ou Java`, `either PHP or .NET` and `au choix`. These stacks are exposed as genuine alternatives and PHP remains a valid route when it matches the configured profile.

Cumulative classification is deliberately stricter. A conjunction such as `and`, `et`, `both`, `ainsi que`, `&` or `+` is treated as cumulative only when the same statement also contains a mandatory cue such as `required`, `mandatory`, `must`, `requis`, `obligatoire` or `indispensable`. For a PHP-oriented profile, a PHP + another mandatory backend stack combination is capped at `60/100` unless a configured target job explicitly targets all of those stacks.

Secondary wording such as `nice-to-have`, `optional`, `bonus`, `apprécié`, `souhaité`, `legacy` or migration context is ignored by this relationship classifier so it cannot override the actual dominant stack.

The score explanation exposes the relationship, for example:

```text
Stack principale détectée : PHP/Symfony ou .NET/C#
Relation des stacks détectée : exigences cumulatives obligatoires
PHP requis avec une autre stack principale : score plafonné à 60/100
```

This parser is intentionally conservative. Ambiguous conjunctions without a clear mandatory or alternative cue fall back to the normal dominant-stack weighting instead of guessing.

A weaker secondary or contextual mention does not override the dominant stack. A Symfony/PHP role that merely mentions Java as useful integration knowledge remains a PHP/Symfony match.

## Cache behavior

Changing the AI prompt and response schema increments the matching cache fingerprint version. Existing cached analyses are therefore not reused after this PHP-relevance classification is introduced; the next valid analysis is stored under the new fingerprint.

## Safety boundary

These guards are local matching behavior only. They do not change connector queries, authentication, external submissions, CAPTCHA handling, source quotas, database schema, or API contracts.

Issue #70 remains covered by deterministic regression fixtures even while the optional AI matcher evolves.
