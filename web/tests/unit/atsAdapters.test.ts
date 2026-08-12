import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type Detection = {
  ats?: { id: string; label: string; confidence: number; reason: string };
  fields: Array<{
    id: string | null;
    name: string | null;
    classification: {
      key: string | null;
      status: string;
      confidence: number;
      evidence: string[];
    };
    ats?: { platform: string; mapped: boolean; conflict?: string };
  }>;
};
type GenericDetector = { detect(documentRef?: Document): Detection };
type Adapters = {
  detectPlatform(documentRef: Document, locationLike?: string | Location): { id: string; confidence: number; reason: string };
  enhanceDetection(detection: Detection, documentRef: Document, locationLike?: string | Location): Detection;
};

const require = createRequire(import.meta.url);
const detector = require('../../../extension/form-detector.js') as GenericDetector;
const adapters = require('../../../extension/ats-adapters.js') as Adapters;

function enhanced(url: string): Detection {
  return adapters.enhanceDetection(detector.detect(document), document, url);
}

function field(result: Detection, id: string) {
  const match = result.fields.find((candidate) => candidate.id === id);
  if (!match) throw new Error(`Missing field ${id}`);
  return match;
}

describe('JobPilot ATS adapters', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  it('maps SmartRecruiters canonical candidate fields from platform identifiers', () => {
    document.body.innerHTML = `
      <input id="first" name="firstName">
      <input id="last" name="lastName">
      <input id="phone" name="phoneNumber">
    `;

    const result = enhanced('https://jobs.smartrecruiters.com/acme/123-role');

    expect(result.ats?.id).toBe('smartrecruiters');
    expect(field(result, 'first').classification.key).toBe('identity.firstName');
    expect(field(result, 'last').classification.key).toBe('identity.lastName');
    expect(field(result, 'phone').classification.key).toBe('identity.phone');
    expect(field(result, 'phone').classification.evidence.some((value) => value.startsWith('ats:smartrecruiters:'))).toBe(true);
  });

  it('maps Greenhouse documented names and keeps question_* fields semantic', () => {
    document.body.innerHTML = `
      <input id="first" name="first_name">
      <input id="last" name="last_name">
      <input id="email" name="email">
      <label for="question">Why do you want to join us?</label>
      <textarea id="question" name="question_2222"></textarea>
    `;

    const result = enhanced('https://job-boards.greenhouse.io/acme/jobs/123');

    expect(result.ats?.id).toBe('greenhouse');
    expect(field(result, 'first').classification.key).toBe('identity.firstName');
    expect(field(result, 'last').classification.key).toBe('identity.lastName');
    expect(field(result, 'email').classification.key).toBe('identity.email');
    expect(field(result, 'question').classification.status).toBe('question');
    expect(field(result, 'question').classification.key).toBeNull();
  });

  it('maps Lever name and urls fields using the hosted application identifiers', () => {
    document.body.innerHTML = `
      <input id="name" name="name">
      <input id="email" name="email">
      <input id="linkedin" name="urls[LinkedIn]">
      <input id="github" name="urls[GitHub]">
    `;

    const result = enhanced('https://jobs.lever.co/acme/abc-123/apply');

    expect(result.ats?.id).toBe('lever');
    expect(field(result, 'name').classification.key).toBe('identity.fullName');
    expect(field(result, 'email').classification.key).toBe('identity.email');
    expect(field(result, 'linkedin').classification.key).toBe('professional.linkedinUrl');
    expect(field(result, 'github').classification.key).toBe('professional.githubUrl');
  });

  it('detects Teamtailor on a custom career domain from its generator marker', () => {
    document.head.innerHTML = '<meta name="generator" content="Teamtailor">';
    document.body.innerHTML = '<input id="first" name="candidate[first_name]">';

    const result = enhanced('https://careers.example.test/jobs/123-role');

    expect(result.ats?.id).toBe('teamtailor');
    expect(result.ats?.confidence).toBeGreaterThanOrEqual(0.9);
    expect(field(result, 'first').classification.key).toBe('identity.firstName');
  });

  it('detects Recruitee from an embedded form marker on a custom domain', () => {
    document.body.innerHTML = `
      <form action="https://company.recruitee.com/o/role/c/new">
        <input id="name" name="candidate[name]">
        <input id="email" name="candidate[email]">
      </form>
    `;

    const result = enhanced('https://jobs.example.test/role');

    expect(result.ats?.id).toBe('recruitee');
    expect(field(result, 'name').classification.key).toBe('identity.fullName');
    expect(field(result, 'email').classification.key).toBe('identity.email');
  });

  it('marks a strong generic/ATS conflict as ambiguous instead of overriding it', () => {
    document.body.innerHTML = `
      <label for="conflict">Last Name</label>
      <input id="conflict" name="first_name">
    `;

    const result = enhanced('https://boards.greenhouse.io/acme/jobs/123');
    const conflict = field(result, 'conflict');

    expect(conflict.classification.status).toBe('ambiguous');
    expect(conflict.classification.key).toBeNull();
    expect(conflict.ats?.conflict).toBe('identity.firstName');
  });

  it('falls back to generic mode when no ATS host or marker is present', () => {
    document.body.innerHTML = '<input id="email" type="email">';

    const result = enhanced('https://jobs.example.test/apply');

    expect(result.ats?.id).toBe('generic');
    expect(field(result, 'email').classification.key).toBe('identity.email');
  });
});
