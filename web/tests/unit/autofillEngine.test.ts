import { createRequire } from 'node:module';

import { beforeEach, describe, expect, it } from 'vitest';

type Detector = { detect(documentRef?: Document): unknown };
type FillReport = {
  filled: number;
  preserved: number;
  review: number;
  skipped: number;
  fields: Array<{
    id: string | null;
    fillStatus: string;
    fillReason?: string | null;
    fillSource?: string;
    valuePreview?: string;
  }>;
};
type Engine = {
  fill(documentRef: Document, context: Record<string, unknown>, detector: Detector): Promise<FillReport>;
};

const require = createRequire(import.meta.url);
const detector = require('../../../extension/form-detector.js') as Detector;
const engine = require('../../../extension/autofill-engine.js') as Engine;

function field(report: FillReport, id: string) {
  const result = report.fields.find((candidate) => candidate.id === id);
  if (!result) throw new Error(`Missing report field ${id}`);
  return result;
}

describe('JobPilot generic autofill engine', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.documentElement.lang = 'fr';
  });

  it('fills recognized empty controls and preserves user-entered values', async () => {
    document.body.innerHTML = `
      <label for="first">Prénom</label>
      <input id="first" autocomplete="given-name">

      <label for="email">E-mail</label>
      <input id="email" type="email" value="already@example.test">

      <label for="country">Pays</label>
      <select id="country" autocomplete="country-name">
        <option value="">Choisir</option>
        <option value="FR">France</option>
        <option value="BE">Belgique</option>
      </select>
    `;

    const report = await engine.fill(document, {
      profile: {
        identity: { firstName: 'Aissa', email: 'aissa@example.test' },
        address: { country: 'France' },
      },
      answers: [],
    }, detector);

    expect((document.getElementById('first') as HTMLInputElement).value).toBe('Aissa');
    expect((document.getElementById('email') as HTMLInputElement).value).toBe('already@example.test');
    expect((document.getElementById('country') as HTMLSelectElement).value).toBe('FR');
    expect(report.filled).toBe(2);
    expect(report.preserved).toBe(1);
    expect(field(report, 'email').fillReason).toBe('already-filled');
  });

  it('blocks sensitive profile-backed answers unless autofill was explicitly allowed', async () => {
    document.body.innerHTML = `
      <fieldset>
        <legend>Êtes-vous autorisé à travailler en France ?</legend>
        <label><input id="auth-yes" type="radio" name="auth" value="yes"> Oui</label>
        <label><input id="auth-no" type="radio" name="auth" value="no"> Non</label>
      </fieldset>
    `;

    const report = await engine.fill(document, {
      profile: { screening: { workAuthorisation: 'Oui' } },
      answers: [{
        key: 'work_authorisation',
        profilePath: 'screening.workAuthorisation',
        enabled: true,
        sensitive: true,
        autoFillAllowed: false,
        eligibleForAutomaticFill: false,
        resolved: { fr: 'Oui', en: 'Yes' },
        questionPatterns: { fr: [], en: [] },
      }],
    }, detector);

    expect((document.getElementById('auth-yes') as HTMLInputElement).checked).toBe(false);
    expect((document.getElementById('auth-no') as HTMLInputElement).checked).toBe(false);
    expect(report.filled).toBe(0);
    expect(report.review).toBe(2);
    expect(field(report, 'auth-yes').fillReason).toBe('sensitive-review-required');
  });

  it('reuses an approved recurring answer for an otherwise unknown yes/no question', async () => {
    document.documentElement.lang = 'en';
    document.body.innerHTML = `
      <fieldset>
        <legend>Would you be willing to relocate?</legend>
        <label><input id="relocate-yes" type="radio" name="relocate" value="yes"> Yes</label>
        <label><input id="relocate-no" type="radio" name="relocate" value="no"> No</label>
      </fieldset>
    `;

    const report = await engine.fill(document, {
      profile: {},
      answers: [{
        key: 'relocation',
        enabled: true,
        sensitive: false,
        autoFillAllowed: true,
        eligibleForAutomaticFill: true,
        resolved: { fr: 'Oui', en: 'Yes' },
        questionPatterns: {
          fr: ['Seriez-vous prêt à déménager ?'],
          en: ['Would you be willing to relocate?'],
        },
      }],
    }, detector);

    expect((document.getElementById('relocate-yes') as HTMLInputElement).checked).toBe(true);
    expect((document.getElementById('relocate-no') as HTMLInputElement).checked).toBe(false);
    expect(report.filled).toBe(1);
    expect(field(report, 'relocate-yes').fillSource).toBe('question:relocation');
  });

  it('does not guess when select options are ambiguous or missing', async () => {
    document.body.innerHTML = `
      <label for="country">Pays</label>
      <select id="country" autocomplete="country-name">
        <option value="">Choisir</option>
        <option value="FR1">France</option>
        <option value="FR2">France</option>
      </select>
    `;

    const report = await engine.fill(document, {
      profile: { address: { country: 'France' } },
      answers: [],
    }, detector);

    expect((document.getElementById('country') as HTMLSelectElement).value).toBe('');
    expect(report.filled).toBe(0);
    expect(report.review).toBe(1);
    expect(field(report, 'country').fillReason).toBe('option-not-found');
  });

  it('selects a unique autocomplete option and rolls back when no valid option exists', async () => {
    document.body.innerHTML = `
      <label for="city">Ville</label>
      <input id="city" role="combobox" aria-autocomplete="list" aria-controls="cities">
      <div id="cities" role="listbox">
        <div id="paris" role="option" data-value="Paris">Paris</div>
        <div role="option" data-value="Lyon">Lyon</div>
      </div>
    `;

    const city = document.getElementById('city') as HTMLInputElement;
    document.getElementById('paris')?.addEventListener('click', () => { city.value = 'Paris'; });

    const filled = await engine.fill(document, {
      profile: { address: { city: 'Paris' } },
      answers: [],
    }, detector);

    expect(city.value).toBe('Paris');
    expect(filled.filled).toBe(1);

    city.value = '';
    const notFound = await engine.fill(document, {
      profile: { address: { city: 'Marseille' } },
      answers: [],
    }, detector);

    expect(city.value).toBe('');
    expect(notFound.filled).toBe(0);
    expect(field(notFound, 'city').fillReason).toBe('autocomplete-option-not-found');
  });
});
