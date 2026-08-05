import { describe, expect, it } from 'vitest';

import { filterCrmOrganizations, normalizeCrmSearch } from '@/lib/crm';
import type { CrmOrganization } from '@/lib/types';

const organizations: CrmOrganization[] = [
  {
    key: 'societe generale',
    name: 'SG France',
    sourceName: 'Société Générale',
    annotation: {
      displayName: 'SG France',
      note: 'Relation prioritaire pour les missions bancaires.',
      updatedAt: '2026-08-05T12:00:00+00:00',
    },
    roles: ['COMPANY', 'CLIENT'],
    offerCount: 1,
    applicationCount: 1,
    positioningCount: 0,
    messageCount: 1,
    contactCount: 1,
    applicationStatuses: { INTERVIEW: 1 },
    positioningStatuses: {},
    lastActivityAt: '2026-08-05T10:00:00+00:00',
    contacts: [{
      key: 'amelie@example.test',
      name: 'Amélie Martin',
      email: 'amelie@example.test',
      phone: '+33 6 10 20 30 40',
      roles: ['RECRUITER'],
      messageCount: 1,
      lastContactAt: '2026-08-05T10:00:00+00:00',
    }],
    latestOffers: [{
      id: 1,
      title: 'Développeur Symfony senior',
      status: 'PREPARED',
      score: 85,
      sourceUrl: 'https://example.test/jobs/1',
    }],
  },
  {
    key: 'tech partners',
    name: 'Tech Partners',
    sourceName: 'Tech Partners',
    annotation: null,
    roles: ['AGENCY'],
    offerCount: 1,
    applicationCount: 0,
    positioningCount: 1,
    messageCount: 0,
    contactCount: 1,
    applicationStatuses: {},
    positioningStatuses: { AGREEMENT_GIVEN: 1 },
    lastActivityAt: '2026-08-04T10:00:00+00:00',
    contacts: [{
      key: 'recruitment@partners.test',
      name: null,
      email: 'recruitment@partners.test',
      phone: null,
      roles: ['APPLICATION_ADDRESS'],
      messageCount: 0,
      lastContactAt: null,
    }],
    latestOffers: [{
      id: 2,
      title: 'Lead React et Next.js',
      status: 'NEW',
      score: 72,
      sourceUrl: null,
    }],
  },
];

describe('CRM directory filtering', () => {
  it('normalizes accents, casing and whitespace', () => {
    expect(normalizeCrmSearch('  Société   GÉNÉRALE ')).toBe('societe generale');
    expect(filterCrmOrganizations(organizations, 'societe generale', 'ALL')).toHaveLength(1);
  });

  it('searches corrected names, source names and CRM notes', () => {
    expect(filterCrmOrganizations(organizations, 'sg france', 'ALL')[0]?.key).toBe('societe generale');
    expect(filterCrmOrganizations(organizations, 'societe generale', 'ALL')[0]?.key).toBe('societe generale');
    expect(filterCrmOrganizations(organizations, 'missions bancaires', 'ALL')[0]?.key).toBe('societe generale');
  });

  it('searches contact names, emails, phone numbers and offer titles', () => {
    expect(filterCrmOrganizations(organizations, 'amelie', 'ALL')[0]?.key).toBe('societe generale');
    expect(filterCrmOrganizations(organizations, 'recruitment@partners.test', 'ALL')[0]?.key).toBe('tech partners');
    expect(filterCrmOrganizations(organizations, '06 10 20', 'ALL')[0]?.key).toBe('societe generale');
    expect(filterCrmOrganizations(organizations, 'next.js', 'ALL')[0]?.key).toBe('tech partners');
  });

  it('combines the organization-role filter with the text query', () => {
    expect(filterCrmOrganizations(organizations, '', 'AGENCY').map((item) => item.key)).toEqual(['tech partners']);
    expect(filterCrmOrganizations(organizations, 'symfony', 'AGENCY')).toEqual([]);
    expect(filterCrmOrganizations(organizations, 'symfony', 'CLIENT').map((item) => item.key)).toEqual(['societe generale']);
  });
});
