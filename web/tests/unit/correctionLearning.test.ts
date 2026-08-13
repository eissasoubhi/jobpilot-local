import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type Learning = {
  applyAndTrack(documentRef: Document, report: Record<string, unknown>, context: Record<string, unknown>, engine: Record<string, unknown>): Record<string, unknown>;
  fieldFingerprint(field: Record<string, unknown>): string;
  promptForCorrection(element: HTMLElement): void;
};

const require = createRequire(import.meta.url);
const learning = require('../../../extension/correction-learning.js') as Learning;
const engine = require('../../../extension/autofill-engine.js') as Record<string, unknown>;

const detectedField = {
  index: 0,
  id: 'city',
  name: 'location',
  label: 'Work location',
  autocomplete: '',
  controlKind: 'select',
  classification: {
    key: 'address.city',
    status: 'recognized',
    confidence: 0.95,
  },
  fillStatus: 'filled',
  fillSource: 'profile:address.city',
};

describe('JobPilot correction learning', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <label for="city">Work location</label>
      <select id="city" name="location">
        <option value="Paris">Paris</option>
        <option value="Cergy">Cergy</option>
        <option value="Lyon">Lyon</option>
      </select>
    `;
  });

  it('applies an already confirmed site correction and marks the field as trackable', () => {
    const select = document.getElementById('city') as HTMLSelectElement;
    select.value = 'Paris';
    const fingerprint = learning.fieldFingerprint(detectedField);

    const report = learning.applyAndTrack(document, {
      fields: [detectedField],
      filled: 1,
      review: 0,
      preserved: 0,
      skipped: 0,
    }, {
      corrections: [{
        id: 7,
        enabled: true,
        fieldFingerprint: fingerprint,
        canonicalKey: 'address.city',
        controlKind: 'select',
        correctedValue: 'Cergy',
      }],
    }, engine) as { fields: Array<Record<string, unknown>> };

    expect(select.value).toBe('Cergy');
    expect(report.fields[0].fillSource).toBe('correction:7');
    expect(report.fields[0].learnedCorrectionApplied).toBe(true);
    expect(select.dataset.jobpilotCorrectionTracked).toBe('1');
    expect(select.dataset.jobpilotAutofillSnapshot).toBe('Cergy');
  });

  it('offers to remember a later user change instead of learning silently', () => {
    const select = document.getElementById('city') as HTMLSelectElement;
    select.value = 'Paris';

    learning.applyAndTrack(document, {
      fields: [detectedField],
      filled: 1,
    }, { corrections: [] }, engine);

    expect(document.querySelector('[data-jobpilot-ui="correction-prompt"]')).toBeNull();

    select.value = 'Lyon';
    learning.promptForCorrection(select);

    const prompt = document.querySelector('[data-jobpilot-ui="correction-prompt"]');
    expect(prompt).not.toBeNull();
    expect(prompt?.textContent).toContain('mémoriser cette correction');
    expect([...prompt!.querySelectorAll('button')].map((button) => button.textContent)).toEqual(['Mémoriser', 'Pas maintenant']);
    expect(select.dataset.jobpilotAutofillSnapshot).toBe('Paris');
  });

  it('does not track screening or compensation fields', () => {
    const select = document.getElementById('city') as HTMLSelectElement;

    learning.applyAndTrack(document, {
      fields: [{
        ...detectedField,
        classification: { ...detectedField.classification, key: 'screening.workAuthorisation' },
      }],
      filled: 1,
    }, { corrections: [] }, engine);
    expect(select.dataset.jobpilotCorrectionTracked).toBeUndefined();

    learning.applyAndTrack(document, {
      fields: [{
        ...detectedField,
        classification: { ...detectedField.classification, key: 'preferences.desiredSalary' },
      }],
      filled: 1,
    }, { corrections: [] }, engine);
    expect(select.dataset.jobpilotCorrectionTracked).toBeUndefined();
  });

  it('uses a stable fingerprint from field semantics rather than the current value', () => {
    const first = learning.fieldFingerprint(detectedField);
    const second = learning.fieldFingerprint({ ...detectedField, fillSource: 'correction:99', valuePreview: 'Other' });

    expect(first).toBe(second);
    expect(first).toContain('select');
    expect(first).toContain('work location');
  });
});
