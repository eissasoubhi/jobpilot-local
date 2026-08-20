import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type Detection = {
  ats?: { id: string };
  fields: Array<unknown>;
};

type Detector = {
  detect(documentRef?: Document): Detection;
};

type AdapterRegistry = {
  enhanceDetection(
    detection: Detection,
    documentRef: Document,
    locationLike?: string | Location,
  ): Detection;
};

type FillReport = {
  filled: number;
  preserved: number;
  review: number;
  skipped: number;
};

type Engine = {
  fill(documentRef: Document, context: Record<string, unknown>, detector: Detector): Promise<FillReport>;
};

type Fixture = {
  name: string;
  url: string;
  head?: string;
  body: string;
  expectedAts: string;
  expectedValues: Record<string, string>;
};

const require = createRequire(import.meta.url);
const genericDetector = require('../../../extension/form-detector.js') as Detector;
const atsAdapters = require('../../../extension/ats-adapters.js') as AdapterRegistry;
const complexAdapters = require('../../../extension/complex-ats-adapters.js') as AdapterRegistry;
const engine = require('../../../extension/autofill-engine.js') as Engine;

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
    body: `
      <form id="application-form">
        <input id="first" name="firstName">
        <input id="last" name="lastName">
        <input id="email" name="email" type="email">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Greenhouse',
    url: 'https://job-boards.greenhouse.io/acme/jobs/123',
    expectedAts: 'greenhouse',
    body: `
      <form id="application-form">
        <input id="first" name="first_name">
        <input id="last" name="last_name">
        <input id="email" name="email" type="email">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Lever',
    url: 'https://jobs.lever.co/acme/abc-123/apply',
    expectedAts: 'lever',
    body: `
      <form id="application-form">
        <input id="name" name="name">
        <input id="email" name="email" type="email">
        <input id="linkedin" name="urls[LinkedIn]">
        <button type="submit">Submit</button>
      </form>
    `,
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
    body: `
      <form id="application-form">
        <input id="first" name="candidate[first_name]">
        <input id="last" name="candidate[last_name]">
        <input id="email" name="candidate[email]" type="email">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: { first: 'Aissa', last: 'Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Recruitee',
    url: 'https://jobs.example.test/role',
    expectedAts: 'recruitee',
    body: `
      <form id="application-form" action="https://company.recruitee.com/o/role/c/new">
        <input id="name" name="candidate[name]">
        <input id="email" name="candidate[email]" type="email">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: { name: 'Aissa Soubhi', email: 'aissa@example.test' },
  },
  {
    name: 'Workday',
    url: 'https://acme.wd5.myworkdayjobs.com/en-US/Careers/job/123',
    expectedAts: 'workday',
    body: `
      <form id="application-form">
        <input id="first" data-automation-id="legalNameSection_firstName">
        <input id="last" data-automation-id="legalNameSection_lastName">
        <input id="email" data-automation-id="emailAddress" type="email">
        <input id="postal" data-automation-id="addressSection_postalCode">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: {
      first: 'Aissa',
      last: 'Soubhi',
      email: 'aissa@example.test',
      postal: '95000',
    },
  },
  {
    name: 'Ashby',
    url: 'https://jobs.ashbyhq.com/acme/123',
    expectedAts: 'ashby',
    body: `
      <form id="application-form">
        <input id="name" name="_systemfield_name">
        <input id="email" name="_systemfield_email" type="email">
        <input id="phone" data-field-path="_systemfield_phoneNumber">
        <button type="submit">Submit</button>
      </form>
    `,
    expectedValues: {
      name: 'Aissa Soubhi',
      email: 'aissa@example.test',
      phone: '+33123456789',
    },
  },
];

function detectorFor(url: string): Detector {
  return {
    detect(documentRef = document) {
      let detection = genericDetector.detect(documentRef);
      detection = atsAdapters.enhanceDetection(detection, documentRef, url);
      detection = complexAdapters.enhanceDetection(detection, documentRef, url);
      return detection;
    },
  };
}

describe('JobPilot Autofill ATS compatibility matrix', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    document.documentElement.lang = 'fr';
  });

  for (const fixture of fixtures) {
    it(`fills the supported ${fixture.name} fixture without submitting`, async () => {
      document.head.innerHTML = fixture.head ?? '';
      document.body.innerHTML = fixture.body;

      let submitCount = 0;
      document.getElementById('application-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCount += 1;
      });

      const detector = detectorFor(fixture.url);
      const detection = detector.detect(document);
      const report = await engine.fill(document, context, detector);

      expect(detection.ats?.id).toBe(fixture.expectedAts);
      for (const [id, expected] of Object.entries(fixture.expectedValues)) {
        expect((document.getElementById(id) as HTMLInputElement).value).toBe(expected);
      }
      expect(report.filled).toBeGreaterThanOrEqual(Object.keys(fixture.expectedValues).length);
      expect(submitCount).toBe(0);
    });
  }

  it('preserves an existing user value across the ATS-aware pipeline', async () => {
    document.body.innerHTML = `
      <label for="email">E-mail</label>
      <input id="email" type="email" value="user-entered@example.test">
    `;

    const report = await engine.fill(
      document,
      context,
      detectorFor('https://job-boards.greenhouse.io/acme/jobs/123'),
    );

    expect((document.getElementById('email') as HTMLInputElement).value).toBe('user-entered@example.test');
    expect(report.preserved).toBe(1);
  });
});
