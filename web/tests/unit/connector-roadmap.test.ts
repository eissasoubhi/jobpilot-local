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

  it('records Jobijoba as an official affiliate-channel plan without enabling scraping', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'jobijoba');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('PLANNED');
    expect(connector?.modes).toEqual(['API']);
    expect(connector?.note).toContain('programme d’affiliation');
    expect(connector?.note).toContain('flux, API ou widget');
    expect(connector?.note).toContain('aucun scraping Jobijoba');
    expect(connector?.nextStep).toContain('spécifications');
    expect(connector?.nextStep).toContain('quotas');
  });

  it('records Talent.com as an official Publisher API plan without enabling scraping', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'talent-com');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('PLANNED');
    expect(connector?.modes).toEqual(['API']);
    expect(connector?.note).toContain('publisher partners');
    expect(connector?.note).toContain('Job API');
    expect(connector?.note).toContain('flux XML ATS');
    expect(connector?.note).toContain('aucun scraping Talent.com');
    expect(connector?.nextStep).toContain('Publisher Job API');
    expect(connector?.nextStep).toContain('credentials');
  });

  it('marks Le Studio Tech as the operational public HTTP scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'le-studio-tech');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('OPERATIONAL');
    expect(connector?.modes).toEqual(['SCRAPING_HTTP']);
    expect(connector?.note).toContain('robots.txt');
  });

  it('keeps sources with restricted automated collection on user-authorized channels', () => {
    for (const code of ['linkedin', 'indeed', 'free-work', 'we-love-devs', 'hellowork', 'welcome-to-the-jungle', 'lesjeudis', 'le-hibou', 'meteojob', 'cadremploi', 'collective-work']) {
      const connector = connectorRoadmap.find((entry) => entry.code === code);

      expect(connector).toBeDefined();
      expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
      expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    }
  });

  it('records why Collective.work does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'collective-work');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
    expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    expect(connector?.note).toContain('re-postage');
    expect(connector?.note).toContain('scraping');
    expect(connector?.note).toContain('utilisation automatisée');
    expect(connector?.nextStep).toContain('Gmail');
  });

  it('records why Cadremploi does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'cadremploi');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
    expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    expect(connector?.note).toContain('bases de données');
    expect(connector?.note).toContain('autorisation écrite');
    expect(connector?.note).toContain('robots');
    expect(connector?.nextStep).toContain('Gmail');
    expect(connector?.nextStep).toContain('Figaro Classifieds');
  });

  it('records why Meteojob does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'meteojob');

    expect(connector).toBeDefined();
    expect(connector?.status).toBe('EMAIL_OR_EXTENSION_ONLY');
    expect(connector?.modes).toEqual(['GMAIL', 'EXTENSION']);
    expect(connector?.note).toContain('personnel et privé');
    expect(connector?.note).toContain('scraping');
    expect(connector?.nextStep).toContain('Gmail');
    expect(connector?.nextStep).toContain('CleverConnect');
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

  it('records why LesJeudis does not get a planned scraper', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'lesjeudis');

    expect(connector).toBeDefined();
    expect(connector?.note).toContain('20/01/2026');
    expect(connector?.note).toContain('logiciel robot');
    expect(connector?.note).toContain('procédé automatisé de scraping');
    expect(connector?.nextStep).toContain('Gmail');
  });

  it('records why LeHibou stays on assisted acquisition', () => {
    const connector = connectorRoadmap.find((entry) => entry.code === 'le-hibou');

    expect(connector).toBeDefined();
    expect(connector?.note).toContain('Aucun API/flux officiel');
    expect(connector?.note).toContain('utilisateurs inscrits');
    expect(connector?.note).toContain('session privée');
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
