import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type Field = {
  id: string | null;
  controlKind: string;
  classification: { key: string | null; status: string; confidence: number; evidence: string[] };
  atsComplex?: { platform: string; mapped: boolean; conflict?: string };
};
type Detection = {
  ats?: { id: string; confidence: number; reason: string };
  fields: Field[];
};
type Detector = { detect(documentRef?: Document): Detection };
type Adapters = {
  detectPlatform(documentRef: Document, locationLike?: string | Location): { id: string; confidence: number; reason: string };
  enhanceDetection(detection: Detection, documentRef: Document, locationLike?: string | Location): Detection;
};

const require = createRequire(import.meta.url);
const detector = require('../../../extension/form-detector.js') as Detector;
const adapters = require('../../../extension/complex-ats-adapters.js') as Adapters;

function enhanced(url: string): Detection {
  return adapters.enhanceDetection(detector.detect(document), document, url);
}

function field(result: Detection, id: string): Field {
  const match = result.fields.find((candidate) => candidate.id === id);
  if (!match) throw new Error(`Missing field ${id}`);
  return match;
}

describe('JobPilot Workday and Ashby adapters', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  it('maps Workday functional automation identifiers without CSS classes', () => {
    document.body.innerHTML = `
      <input id="wd-first" data-automation-id="legalNameSection_firstName">
      <input id="wd-last" data-automation-id="legalNameSection_lastName">
      <input id="wd-email" data-automation-id="emailAddress">
      <input id="wd-phone" data-automation-id="phone-number">
      <input id="wd-postal" data-automation-id="addressSection_postalCode">
    `;

    const result = enhanced('https://acme.wd5.myworkdayjobs.com/en-US/Careers/job/123');

    expect(result.ats?.id).toBe('workday');
    expect(field(result, 'wd-first').classification.key).toBe('identity.firstName');
    expect(field(result, 'wd-last').classification.key).toBe('identity.lastName');
    expect(field(result, 'wd-email').classification.key).toBe('identity.email');
    expect(field(result, 'wd-phone').classification.key).toBe('identity.phone');
    expect(field(result, 'wd-postal').classification.key).toBe('address.postalCode');
  });

  it('maps a Workday country combobox while leaving widget handling to the generic engine', () => {
    document.body.innerHTML = `
      <input id="country" role="combobox" aria-autocomplete="list" data-automation-id="addressSection_countryRegion" aria-controls="countries">
      <div id="countries" role="listbox">
        <div role="option">France</div>
        <div role="option">Belgium</div>
      </div>
    `;

    const result = enhanced('https://acme.wd1.myworkdayjobs.com/Careers/job/123');
    const country = field(result, 'country');

    expect(country.controlKind).toBe('autocomplete');
    expect(country.classification.key).toBe('address.country');
    expect(country.atsComplex?.platform).toBe('workday');
  });

  it('maps Ashby system name and email fields from public system paths', () => {
    document.body.innerHTML = `
      <input id="ashby-name" name="_systemfield_name">
      <input id="ashby-email" name="_systemfield_email">
      <input id="ashby-phone" data-field-path="_systemfield_phoneNumber">
      <input id="ashby-resume" type="file" name="_systemfield_resume">
    `;

    const result = enhanced('https://jobs.ashbyhq.com/acme/123');

    expect(result.ats?.id).toBe('ashby');
    expect(field(result, 'ashby-name').classification.key).toBe('identity.fullName');
    expect(field(result, 'ashby-email').classification.key).toBe('identity.email');
    expect(field(result, 'ashby-phone').classification.key).toBe('identity.phone');
    expect(field(result, 'ashby-resume').classification.key).toBeNull();
  });

  it('detects an embedded Ashby form on a custom careers domain', () => {
    document.body.innerHTML = `
      <form>
        <input id="name" data-field-path="_systemfield_name">
      </form>
    `;

    const result = enhanced('https://careers.example.test/jobs/123');

    expect(result.ats?.id).toBe('ashby');
    expect(result.ats?.confidence).toBeGreaterThanOrEqual(0.9);
    expect(field(result, 'name').classification.key).toBe('identity.fullName');
  });

  it('keeps dynamic Ashby questions semantic instead of inventing a profile mapping', () => {
    document.body.innerHTML = `
      <label for="question">Why are you interested in this role?</label>
      <textarea id="question" data-field-path="custom-interest"></textarea>
    `;

    const result = enhanced('https://jobs.ashbyhq.com/acme/123');
    const question = field(result, 'question');

    expect(question.classification.status).toBe('question');
    expect(question.classification.key).toBeNull();
  });

  it('turns a strong Workday attribute/label conflict into review', () => {
    document.body.innerHTML = `
      <label for="conflict">Last Name</label>
      <input id="conflict" data-automation-id="legalNameSection_firstName">
    `;

    const result = enhanced('https://acme.wd5.myworkdayjobs.com/Careers/job/123');
    const conflict = field(result, 'conflict');

    expect(conflict.classification.status).toBe('ambiguous');
    expect(conflict.classification.key).toBeNull();
    expect(conflict.atsComplex?.conflict).toBe('identity.firstName');
  });
});
