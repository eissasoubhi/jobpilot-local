import { beforeEach, describe, expect, it } from 'vitest';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const learning = require('../../../extension/correction-learning.js');
// eslint-disable-next-line @typescript-eslint/no-require-imports
const detector = require('../../../extension/form-detector.js');

describe('JobPilot correction learning', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    window.history.replaceState({}, '', '/apply');
  });

  it('creates a stable site-specific fingerprint for a recognized field', () => {
    document.body.innerHTML = '<label for="first">Prénom</label><input id="first" name="first_name">';
    const field = detector.detect(document).fields[0];

    expect(field.classification.key).toBe('identity.firstName');
    expect(learning.fieldFingerprint(field)).toContain('identity firstname');
    expect(learning.isLearnableField(field)).toBe(true);
  });

  it('never learns sensitive or ambiguous fields', () => {
    document.body.innerHTML = `
      <label for="salary">Salaire souhaité</label><input id="salary" name="salary">
      <label for="visa">Visa sponsorship</label><input id="visa" name="sponsorship">
    `;
    const fields = detector.detect(document).fields;

    expect(fields[0].classification.key).toBe('preferences.desiredSalary');
    expect(fields[1].classification.key).toBe('screening.sponsorship');
    expect(learning.isLearnableField(fields[0])).toBe(false);
    expect(learning.isLearnableField(fields[1])).toBe(false);
  });

  it('applies an approved rule only to the same domain and exact field', () => {
    document.body.innerHTML = '<label for="city">Ville</label><input id="city" name="city">';
    const field = detector.detect(document).fields[0];
    const fingerprint = learning.fieldFingerprint(field);
    const domain = learning.domainFor(document.location);
    const rules = [{
      id: `${domain}:${fingerprint}`,
      domain,
      fingerprint,
      key: 'address.city',
      label: 'Ville',
      value: 'Cergy',
      controlKind: 'text',
      enabled: true,
    }];

    const result = learning.applyRules(document, detector, rules);

    expect(result.applied).toBe(1);
    expect(result.appliedFingerprints).toEqual([fingerprint]);
    expect((document.getElementById('city') as HTMLInputElement).value).toBe('Cergy');

    const wrongDomain = learning.applyRules(document, detector, [{ ...rules[0], domain: `other.${domain || 'example.com'}`, value: 'Paris' }]);
    expect(wrongDomain.applied).toBe(0);
    expect((document.getElementById('city') as HTMLInputElement).value).toBe('Cergy');
  });

  it('updates an existing rule instead of accumulating duplicates', () => {
    const original = [{ domain: 'jobs.example.com', fingerprint: 'field-1', value: 'Paris', enabled: true }];
    const updated = learning.upsertRule(original, {
      domain: 'jobs.example.com',
      fingerprint: 'field-1',
      value: 'Cergy',
      enabled: true,
    });

    expect(updated).toHaveLength(1);
    expect(updated[0].value).toBe('Cergy');
  });
});
