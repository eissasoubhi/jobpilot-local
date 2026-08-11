import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type DetectedField = {
  id: string | null;
  controlKind: string;
  options: Array<{ value: string; label: string; disabled: boolean }>;
  classification: {
    key: string | null;
    confidence: number;
    status: 'recognized' | 'ambiguous' | 'question' | 'unknown';
    questionText: string | null;
    evidence: string[];
  };
};

type DetectorResult = {
  fieldCount: number;
  recognizedCount: number;
  questionCount: number;
  unknownCount: number;
  fields: DetectedField[];
};

type Detector = {
  detect(documentRef?: Document): DetectorResult;
};

const require = createRequire(import.meta.url);
const detector = require('../../../extension/form-detector.js') as Detector;

function byId(result: DetectorResult, id: string): DetectedField {
  const field = result.fields.find((candidate) => candidate.id === id);
  if (!field) throw new Error(`Missing detected field ${id}`);
  return field;
}

describe('JobPilot generic form detector', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.title = 'Application test';
  });

  it('classifies identity and address fields from labels, autocomplete, names and placeholders', () => {
    document.body.innerHTML = `
      <form>
        <label for="first">Prénom</label>
        <input id="first" name="candidate_first_name" autocomplete="given-name">

        <label for="last">Nom de famille</label>
        <input id="last" name="candidate_last_name" autocomplete="family-name">

        <input id="email" type="email" name="email" placeholder="Votre e-mail">
        <input id="phone" type="tel" aria-label="Téléphone mobile">

        <label for="city">Ville</label>
        <input id="city" autocomplete="address-level2">

        <label for="zip">Code postal</label>
        <input id="zip" name="postal_code">
      </form>
    `;

    const result = detector.detect(document);

    expect(result.fieldCount).toBe(6);
    expect(result.recognizedCount).toBe(6);
    expect(byId(result, 'first').classification.key).toBe('identity.firstName');
    expect(byId(result, 'first').classification.confidence).toBeGreaterThan(0.9);
    expect(byId(result, 'last').classification.key).toBe('identity.lastName');
    expect(byId(result, 'email').classification.key).toBe('identity.email');
    expect(byId(result, 'phone').classification.key).toBe('identity.phone');
    expect(byId(result, 'city').classification.key).toBe('address.city');
    expect(byId(result, 'zip').classification.key).toBe('address.postalCode');
  });

  it('uses ARIA, fieldset context and exposes select options without choosing a value', () => {
    document.body.innerHTML = `
      <form>
        <span id="country-label">Pays de résidence</span>
        <select id="country" aria-labelledby="country-label" autocomplete="country-name">
          <option value="">Choisir</option>
          <option value="FR">France</option>
          <option value="BE" disabled>Belgique</option>
        </select>

        <fieldset>
          <legend>Êtes-vous autorisé à travailler en France ?</legend>
          <label><input id="work-yes" type="radio" name="work_auth" value="yes"> Oui</label>
          <label><input id="work-no" type="radio" name="work_auth" value="no"> Non</label>
        </fieldset>
      </form>
    `;

    const result = detector.detect(document);
    const country = byId(result, 'country');
    const workYes = byId(result, 'work-yes');

    expect(country.controlKind).toBe('select');
    expect(country.classification.key).toBe('address.country');
    expect(country.options).toEqual([
      { value: '', label: 'Choisir', disabled: false },
      { value: 'FR', label: 'France', disabled: false },
      { value: 'BE', label: 'Belgique', disabled: true },
    ]);
    expect(workYes.controlKind).toBe('radio');
    expect(workYes.classification.key).toBe('screening.workAuthorisation');
  });

  it('recognizes combobox-style autocomplete controls', () => {
    document.body.innerHTML = `
      <label for="location">City</label>
      <input id="location" role="combobox" aria-autocomplete="list" aria-controls="locations">
      <div id="locations" role="listbox">
        <div role="option" data-value="paris">Paris</div>
        <div role="option" data-value="lyon">Lyon</div>
      </div>
    `;

    const field = byId(detector.detect(document), 'location');

    expect(field.controlKind).toBe('autocomplete');
    expect(field.classification.key).toBe('address.city');
    expect(field.options.map((option) => option.label)).toEqual(['Paris', 'Lyon']);
  });

  it('keeps free-form screening questions distinct from known profile fields', () => {
    document.body.innerHTML = `
      <label for="motivation">Why do you want to join our company?</label>
      <textarea id="motivation" name="motivation"></textarea>
      <label for="other">Internal reference</label>
      <input id="other" name="internal_reference">
    `;

    const result = detector.detect(document);
    const motivation = byId(result, 'motivation');
    const other = byId(result, 'other');

    expect(motivation.classification.status).toBe('question');
    expect(motivation.classification.key).toBeNull();
    expect(motivation.classification.questionText).toContain('Why do you want');
    expect(other.classification.status).toBe('unknown');
    expect(result.questionCount).toBe(1);
    expect(result.unknownCount).toBe(1);
  });

  it('ignores credentials and non-data controls while retaining file inputs for later document upload support', () => {
    document.body.innerHTML = `
      <input id="hidden" type="hidden" name="token">
      <input id="password" type="password" name="password">
      <input id="submit" type="submit" value="Send">
      <label for="cv">CV</label>
      <input id="cv" type="file" name="resume">
    `;

    const result = detector.detect(document);

    expect(result.fieldCount).toBe(1);
    expect(byId(result, 'cv').controlKind).toBe('file');
  });
});
