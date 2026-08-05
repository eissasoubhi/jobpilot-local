# Connector-specific quality profiles

## Purpose

A field can be essential for one source and merely useful for another. JobPilot therefore applies a connector-specific quality profile before falling back to the global defaults.

## Defaults

Required fields:

```text
externalId
title
description
```

Recommended fields:

```text
company
sourceUrl
location
contractType
publishedAt
```

## Symfony Jobs

The official Symfony Jobs feed is expected to provide a stable direct link for every entry. Its profile therefore makes `sourceUrl` required:

```text
required: externalId, title, description, sourceUrl
recommended: company, location, contractType, publishedAt
```

A missing direct URL now degrades connector health instead of being treated as a minor metadata gap.

## Resolution

`ConnectorQualityProfileRegistry` resolves a profile from the normalized source name already present in each connector payload. `ConnectorPayloadQualityAnalyzer` records the applied rules in every synchronization diagnostic under:

```text
fieldQuality.rules.profile
fieldQuality.rules.required
fieldQuality.rules.recommended
```

Explicit rule lists passed by a caller take precedence over the registered profile. Required and recommended lists are normalized, deduplicated, and cannot contain the same field.

## Safety

Quality profiles are read-only validation rules. They do not contact external sources, change connector permissions, bypass quotas, or reject previously stored offers. They only affect diagnostics and connector health for future synchronization runs.

## Adding a profile

1. Identify a stable field guaranteed by the source contract or official feed.
2. Add the profile to `ConnectorQualityProfileRegistry`.
3. Add unit tests covering missing required and recommended fields.
4. Document why the field is expected.
5. Avoid promoting optional data to required without evidence from the source contract.
