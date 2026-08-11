import { describe, expect, it } from 'vitest';

import { connectorRoadmap } from '../../lib/connector-roadmap';

const requestedPlatformNames = [
  'LinkedIn',
  'Malt',
  'Free-Work',
  'Apec',
  'Collective.work',
  'Crème de la Crème',
  'FreelanceRepublik',
  'Comet',
  'Cherry Pick',
  'LeHibou',
  'Mindquest',
  'WeLoveDevs',
  'Sept Lieues',
  'Jean-Michel.io',
  'Welcome to the Jungle',
  'Cadremploi',
  'HelloWork',
  'Jobijoba',
  'EURES',
  'Freelance-Informatique',
  'Indeed',
  'Adzuna',
  'Kicklox',
  'Talent.com',
  'SmartRecruiters',
  'GetYourJob',
  'Le Studio Tech',
  'Meteojob',
  'Michael Page',
  'France Travail',
] as const;

describe('platform acquisition matrix', () => {
  it('uses stable unique source codes and covers every requested platform', () => {
    const codes = connectorRoadmap.map((connector) => connector.code);
    const names = connectorRoadmap.map((connector) => connector.name);

    expect(new Set(codes).size).toBe(codes.length);
    for (const name of requestedPlatformNames) {
      expect(names).toContain(name);
    }
    expect(names).toContain('LesJeudis');
  });

  it('contains only explicit acquisition states', () => {
    expect(connectorRoadmap.every((connector) => (
      connector.status === 'OPERATIONAL'
      || connector.status === 'PLANNED'
      || connector.status === 'UNDER_REVIEW'
      || connector.status === 'EMAIL_OR_EXTENSION_ONLY'
    ))).toBe(true);
  });

  it('marks registered API connectors as operational', () => {
    for (const code of ['adzuna', 'france-travail', 'smartrecruiters']) {
      const connector = connectorRoadmap.find((entry) => entry.code === code);

      expect(connector).toBeDefined();
      expect(connector?.status).toBe('OPERATIONAL');
      expect(connector?.modes).toEqual(['API']);
    }
  });

  it('records Apec as an official partner-feed plan without enabling scraping', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'apec');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('PLANNED');
    expect(connector?.modes).toEqual(['XML', 'GMAIL', 'EXTENSION']);
    expect(connector?.note).toContain('flux XML standardisé');
    expect(connector?.note).toContain('convention de partenariat');
    expect(connector?.note).toContain('aucun scraping Apec');
    expect(connector?.nextStep).toContain('convention de partenariat Apec');
  });

  it('marks Le Studio Tech as the operational public HTTP scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'le-studio-tech');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('OPERATIONAL');
    expect(connector?.modes).toEqual(['SCRAPING_HTTP']);
    expect(connector?.note).toContain('robots.txt');
  });

  it('keeps sources with restricted automated collection on user-authorized channels', () => {
    for (const code of ['linkedin', 'indeed', 'free-work', 'we-love-devs', 'hellowork', 'welcome-to-the-jungle']) {
      const connector = connectorRoadmap.find((entry) => entry.code === code);

      expect(connector).toBeDefined();
      expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
      expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    }
  });

  it('records why HelloWork does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'hellowork');

    expect(connector).toBeDefined();
    expect(connector?.note).toContain('screen/web scraping');
    expect(connector?.note).toContain('commerciales ou non');
    expect(connector?.note).toContain('licence écrite');
    expect(connector?.nextStep).toContain('Gmail');
  });

  it('records why Welcome to the Jungle does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'welcome-to-the-jungle');

    expect(connector).toBeDefined();
    expect(connector?.note).toContain('27/04/2026');
    expect(connector?.note).toContain('robots');
    expect(connector?.note).toContain('extensions/modules de navigateur');
    expect(connector?.nextStep).toContain('Gmail');
  });

  it('records why WeLoveDevs does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'we-love-devs');

    expect(connector).toBeDefined();
    expect(connector?.note).toContain('CGU');
    expect(connector?.note).toContain('Aucun scraping planifié');
    expect(connector?.nextStep).toContain('autorisation écrite');
  });

  it('describes SmartRecruiters as a configured official API connector', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'smartrecruiters');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('OPERATIONAL');
    expect(connector?.modes).toEqual(['API']);
    expect(connector?.note).toContain('jeton');
    expect(connector?.nextStep).toContain('entreprises');
  });

  it('does not invent a reusable EURES channel before it is confirmed', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'eures');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('UNDER_REVIEW');
    expect(connector?.modes).toEqual([]);
  });
});
