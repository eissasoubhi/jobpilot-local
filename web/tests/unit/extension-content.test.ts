/**
 * @vitest-environment jsdom
 * @vitest-environment-options {"url":"https://www.free-work.com/fr/tech-it/job-mission/developpeur-php/test-offer"}
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

interface ExtractedOffer {
  url: string;
  source: string;
  sourceCode: string;
  externalId: string;
  title: string;
  company: string;
  location: string;
  contractType: string;
  workMode: string;
  description: string;
  publishedAt: string | null;
  extractionMethod: string;
  tjmMin?: number;
  tjmMax?: number;
  salaryMin?: number;
  salaryMax?: number;
}

type ExtensionListener = (
  message: { type: string },
  sender: unknown,
  sendResponse: (response: ExtractedOffer) => void,
) => void;

describe('JobPilot extension offer extraction', () => {
  let listener: ExtensionListener;

  beforeAll(() => {
    if (!Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'innerText')?.get) {
      Object.defineProperty(HTMLElement.prototype, 'innerText', {
        configurable: true,
        get(this: HTMLElement) {
          return this.textContent || '';
        },
      });
    }

    const addListener = vi.fn((registered: ExtensionListener) => {
      listener = registered;
    });

    Object.assign(globalThis, {
      chrome: {
        runtime: {
          onMessage: { addListener },
        },
      },
    });

    const script = readFileSync(resolve(process.cwd(), '../extension/content.js'), 'utf8');
    window.eval(script);
    expect(addListener).toHaveBeenCalledOnce();
  });

  beforeEach(() => {
    document.head.innerHTML = '<link rel="canonical" href="https://www.free-work.com/fr/tech-it/job-mission/developpeur-php/test-offer">';
    document.body.innerHTML = '';
  });

  function extract(): ExtractedOffer {
    let response: ExtractedOffer | undefined;
    listener({ type: 'EXTRACT_PAGE' }, {}, payload => {
      response = payload;
    });

    expect(response).toBeDefined();
    return response as ExtractedOffer;
  }

  it('prefers JobPosting JSON-LD and keeps Free-Work as the source', () => {
    document.body.innerHTML = `
      <script type="application/ld+json">
        ${JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'JobPosting',
          title: 'Développeur Senior PHP Symfony',
          description: '<p>Mission Symfony API Platform et PostgreSQL.</p>',
          datePosted: '2026-08-04',
          employmentType: 'CONTRACTOR',
          hiringOrganization: { '@type': 'Organization', name: 'Softeam' },
          jobLocation: {
            '@type': 'Place',
            address: {
              '@type': 'PostalAddress',
              addressLocality: 'Paris',
              addressRegion: 'Île-de-France',
              addressCountry: 'France',
            },
          },
          baseSalary: {
            '@type': 'MonetaryAmount',
            currency: 'EUR',
            value: { '@type': 'QuantitativeValue', minValue: 450, maxValue: 520, unitText: 'DAY' },
          },
        })}
      </script>
      <main>
        <h1>Mission freelance Développeur Senior PHP Symfony</h1>
        <h2>Île-de-France</h2>
        <p>Softeam</p>
        <p>Télétravail partiel</p>
      </main>
    `;

    const offer = extract();

    expect(offer).toMatchObject({
      source: 'Free-Work',
      sourceCode: 'free-work',
      externalId: 'free-work-test-offer',
      title: 'Développeur Senior PHP Symfony',
      company: 'Softeam',
      location: 'Paris, Île-de-France, France',
      contractType: 'Freelance',
      workMode: 'Hybride',
      description: 'Mission Symfony API Platform et PostgreSQL.',
      publishedAt: '2026-08-04T00:00:00.000Z',
      extractionMethod: 'job-posting-json-ld',
      tjmMin: 450,
      tjmMax: 520,
    });
  });

  it('falls back to visible Free-Work content when structured data is absent', () => {
    document.body.innerHTML = `
      <main>
        <h1>Offre d'emploi Développeur PHP Symfony (H/F)</h1>
        <h2>Marseille, Provence-Alpes-Côte d'Azur</h2>
        <p>Amiltone</p>
        <p>Publiée le 03/08/2026</p>
        <p>CDI — Salaire 40k-45k €⁄an — Télétravail partiel</p>
        <section>Développement PHP Symfony, tests automatisés, Git, Jenkins et PostgreSQL.</section>
      </main>
    `;

    const offer = extract();

    expect(offer).toMatchObject({
      source: 'Free-Work',
      sourceCode: 'free-work',
      title: 'Développeur PHP Symfony (H/F)',
      company: 'Amiltone',
      location: "Marseille, Provence-Alpes-Côte d'Azur",
      contractType: 'CDI',
      workMode: 'Hybride',
      publishedAt: '2026-08-03',
      extractionMethod: 'free-work-visible-page',
      salaryMin: 40000,
      salaryMax: 45000,
    });
    expect(offer.description).toContain('Développement PHP Symfony');
  });
});
