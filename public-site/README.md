# JobPilot public presentation site

This directory contains a static public site for presenting JobPilot to API providers such as France Travail.

It is intentionally separate from the local application:

- no CV, application, Gmail message or user profile is exposed;
- no API secret is included;
- no form, cookie, analytics script or client-side JavaScript is used;
- the public site does not proxy requests to the local JobPilot API.

## Deploy with Vercel

1. Merge this directory into the default branch.
2. In Vercel, create a new Project and import `eissasoubhi/jobpilot-local`.
3. Set **Root Directory** to `public-site`.
4. Keep the framework preset on **Other**.
5. Do not configure a build command or environment variable.
6. Deploy.
7. Open the generated HTTPS URL and verify:
   - `/`
   - `/confidentialite`
8. Use the final production URL in the France Travail.io application form.

The `vercel.json` file enables clean URLs and adds restrictive security headers. The site can also be hosted by any static HTTPS provider.

## Content rules

The public wording must remain factual:

- state that France Travail API usage is planned until credentials are obtained and the connector is enabled;
- do not claim a partnership, approval or sponsorship;
- do not publish API identifiers or secrets;
- keep the privacy page aligned with any future analytics, contact form or external service added to the public site.

## Local preview

From this directory, serve the files with any static HTTP server. For example:

```bash
python3 -m http.server 4173
```

Then open `http://localhost:4173`.
