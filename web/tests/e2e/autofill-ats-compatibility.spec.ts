import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { expect, test } from '@playwright/test';

type Fixture = {
  name: string;
  url: string;
  head?: string;
  body: string;
  expectedAts: string;
  expectedValues: Record<string, string>;
};

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionDirectory = path.resolve(testDirectory, '../../../extension');
const extensionScripts = [
  'form-detector.js',
  'ats-adapters.js',
  'complex-ats-adapters.js',
  'autofill-engine.js',
].map((name) => fs.readFileSync(path.join(extensionDirectory, name), 'utf8'));

const context = {
  profile: {
    identity: {
      firstName: 'Aissa',
      lastName: 'Soubhi',
      fullName: 'Aissa Soubhi',
      email: 'aissa@example.test',
      phone: '+33123456789',
    },
    address: {
      country: 'France',
      postalCode: '95000',
    },
    professional: {
      linkedinUrl: 'https://www.linkedin.com/in/aissa-test',
      githubUrl: 'https://github.com/aissa-test',
    },
  },
  answers: [],
};

const fixtures: Fixture[] = [
  {
    name: 'SmartRecruiters',
    url: 'https://jobs.smartrecruiters.com/acme/123-role',
    expectedAts: 'smartrecruiters',
    body: '<form id="application-form"><input id="first" name="firstName"><input id="last" name="lastName"><input id="email" name="email" type="email"><button type="submit">Submit</button></form>',
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Greenhouse',
    url: 'https://job-boards.greenhouse.io/acme/jobs/123',
    expectedAts: 'greenhouse',
    body: '<form id="application-form"><input id="first" name="first_name"><input id="last" name="last_name"><input id="email" name="email" type="email"><button type="submit">Submit</button></form>',
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Lever',
    url: 'https://jobs.lever.co/acme/abc-123/apply',
    expectedAts: 'lever',
    body: '<form id="application-form"><input id="name" name="name"><input id="email" name="email" type="email"><input id="linkedin" name="urls[LinkedIn]"><button type="submit">Submit</button></form>',
    expectedValues: {
      name: 'Aissa Soubhi',
      email: 'aissa@example.test',
      linkedin: 'https://www.linkedin.com/in/aissa-test',
    },
  },
  {
    name: 'Teamtailor',
    url: 'https://careers.example.test/jobs/123-role',
    expectedAts: 'teamtailor',
    head: '<meta name="generator" content="Teamtailor">',
    body: '<form id="application-form"><input id="first" name="candidate[first_name]"><input id="last" name="candidate[last_name]"><input id="email" name="candidate[email]" type="email"><button type="submit">Submit</button></form>',
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Recruitee',
    url: 'https://jobs.example.test/role',
    expectedAts: 'recruitee',
    body: '<form id="application-form" action="https://company.recruitee.com/o/role/c/new"><input id="name" name="candidate[name]"><input id="email" name="candidate[email]" type="email"><button type="submit">Submit</button></form>',
    expectedValues: { name: 'Aissa Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Workday',
    url: 'https://acme.wd5.myworkdayjobs.com/en-US/Careers/job/123',
    expectedAts: 'workday',
    body: '<form id="application-form"><input id="first" data-automation-id="legalNameSection_firstName"><input id="last" data-automation-id="legalNameSection_lastName"><input id="email" data-automation-id="emailAddress" type="email"><input id="postal" data-automation-id="addressSection_postalCode"><button type="submit">Submit</button></form>',
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test', postal: '95000' },
  },
  {
    name: 'Ashby',
    url: 'https://jobs.ashbyhq.com/acme/123',
    expectedAts: 'ashby',
    body: '<form id="application-form"><input id="name" name="_systemfield_name"><input id="email" name="_systemfield_email" type="email"><input id="phone" data-field-path="_systemfield_phoneNumber"><button type="submit">Submit</button></form>',
    expectedValues: { name: 'Aissa Soubhi', email: 'aissa@example.test', phone: '+33123456789' },
  },
];

async function loadAutofillScripts(page: import('@playwright/test').Page): Promise<void> {
  for (const script of extensionScripts) {
    await page.addScriptTag({ content: script });
  }
}

for (const fixture of fixtures) {
  test(`Autofill compatibility: ${fixture.name}`, async ({ page }) => {
    await page.setContent(`<!doctype html><html lang="fr"><head>${fixture.head ?? ''}</head><body>${fixture.body}</body></html>`);
    await loadAutofillScripts(page);

    const result = await page.evaluate(async ({ url, candidateContext, fieldIds }) => {
      const root = globalThis as typeof globalThis & {
        JobPilotFormDetector: { detect(documentRef: Document): { ats?: { id: string }; fields: unknown[] } };
        JobPilotAtsAdapters: {
          enhanceDetection(detection: unknown, documentRef: Document, locationLike: string): unknown;
        };
        JobPilotComplexAtsAdapters: {
          enhanceDetection(detection: unknown, documentRef: Document, locationLike: string): unknown;
        };
        JobPilotAutofillEngine: {
          fill(documentRef: Document, context: Record<string, unknown>, detector: { detect(documentRef: Document): unknown }): Promise<{ filled: number; preserved: number }>;
        };
      };

      let submitCount = 0;
      document.getElementById('application-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCount += 1;
      });

      let detection = root.JobPilotFormDetector.detect(document);
      detection = root.JobPilotAtsAdapters.enhanceDetection(detection, document, url) as typeof detection;
      detection = root.JobPilotComplexAtsAdapters.enhanceDetection(detection, document, url) as typeof detection;

      const report = await root.JobPilotAutofillEngine.fill(
        document,
        candidateContext,
        { detect: () => detection },
      );

      return {
        ats: detection.ats?.id ?? null,
        report,
        submitCount,
        values: Object.fromEntries(fieldIds.map((id) => [id, (document.getElementById(id) as HTMLInputElement | null)?.value ?? null])),
      };
    }, {
      url: fixture.url,
      candidateContext: context,
      fieldIds: Object.keys(fixture.expectedValues),
    });

    expect(result.ats).toBe(fixture.expectedAts);
    expect(result.values).toEqual(fixture.expectedValues);
    expect(result.report.filled).toBeGreaterThanOrEqual(Object.keys(fixture.expectedValues).length);
    expect(result.submitCount).toBe(0);
  });
}

test('Autofill compatibility preserves an existing user value', async ({ page }) => {
  await page.setContent('<!doctype html><html lang="fr"><body><label for="email">E-mail</label><input id="email" type="email" value="user-entered@example.test"></body></html>');
  await loadAutofillScripts(page);

  const result = await page.evaluate(async (candidateContext) => {
    const root = globalThis as typeof globalThis & {
      JobPilotFormDetector: { detect(documentRef: Document): unknown };
      JobPilotAtsAdapters: { enhanceDetection(detection: unknown, documentRef: Document, locationLike: string): unknown };
      JobPilotComplexAtsAdapters: { enhanceDetection(detection: unknown, documentRef: Document, locationLike: string): unknown };
      JobPilotAutofillEngine: {
        fill(documentRef: Document, context: Record<string, unknown>, detector: { detect(documentRef: Document): unknown }): Promise<{ preserved: number }>;
      };
    };

    let detection = root.JobPilotFormDetector.detect(document);
    detection = root.JobPilotAtsAdapters.enhanceDetection(detection, document, 'https://job-boards.greenhouse.io/acme/jobs/123');
    detection = root.JobPilotComplexAtsAdapters.enhanceDetection(detection, document, 'https://job-boards.greenhouse.io/acme/jobs/123');
    const report = await root.JobPilotAutofillEngine.fill(document, candidateContext, { detect: () => detection });

    return {
      value: (document.getElementById('email') as HTMLInputElement).value,
      preserved: report.preserved,
    };
  }, context);

  expect(result.value).toBe('user-entered@example.test');
  expect(result.preserved).toBe(1);
});
