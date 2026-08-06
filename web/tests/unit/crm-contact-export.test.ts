import { describe, expect, it } from 'vitest';

import { createCrmContactsCsv } from '@/lib/crm-contact-export';
import type { CrmOrganization } from '@/lib/types';

const organization = {
  key: 'acme',
  name: 'Acme; France',
  sourceName: 'Acme',
  roles: ['COMPANY'],
  offerCount: 0,
  applicationCount: 0,
  positioningCount: 0,
  messageCount: 0,
  contactCount: 1,
  applicationStatuses: {},
  positioningStatuses: {},
  contacts: [],
  latestOffers: [],
} as CrmOrganization;

describe('createCrmContactsCsv', () => {
  it('exports displayed and source values with Excel-compatible separators', () => {
    const csv = createCrmContactsCsv([{
      organization,
      organizationName: organization.name,
      contact: {
        key: 'jane@example.com',
        name: 'Jane "JJ" Doe',
        email: 'jane@example.com',
        phone: '+33 6 00 00 00 00',
        roles: ['RECRUITER', 'INBOX_CONTACT'],
        messageCount: 2,
        lastContactAt: '2026-08-05T10:00:00+02:00',
        sourceName: 'Jane Doe',
        sourceEmail: 'source@example.com',
        sourcePhone: null,
        correction: { correctedName: 'Jane "JJ" Doe' },
      },
    }]);

    expect(csv.startsWith('\uFEFF')).toBe(true);
    expect(csv).toContain('"Acme; France"');
    expect(csv).toContain('"Jane ""JJ"" Doe"');
    expect(csv).toContain('"RECRUITER | INBOX_CONTACT"');
    expect(csv).toContain('"Oui"');
    expect(csv).toContain('"Jane Doe";"source@example.com"');
    expect(csv.endsWith('\r\n')).toBe(true);
  });

  it('neutralizes spreadsheet formulas in every exported cell', () => {
    const csv = createCrmContactsCsv([{
      organization,
      organizationName: '=HYPERLINK("https://example.test")',
      contact: {
        key: 'danger',
        name: '+SUM(1,1)',
        email: null,
        phone: '-2+3',
        roles: ['RECRUITER'],
        messageCount: 0,
        sourceName: '@cmd',
      },
    }]);

    expect(csv).toContain('"\'=HYPERLINK(""https://example.test"")"');
    expect(csv).toContain('"\'+SUM(1,1)"');
    expect(csv).toContain('"\'-2+3"');
    expect(csv).toContain('"\'@cmd"');
  });
});
