import { describe, expect, it } from 'vitest';

import { filterCrmContacts } from '@/lib/crm-contact-filters';

const contacts = [
  {
    organizationName: 'Élite Conseil',
    contact: {
      name: 'Aïcha Recruteuse',
      email: 'aicha@example.test',
      phone: '+33 6 10 20 30 40',
      sourceName: 'Aicha',
      sourceEmail: 'source@example.test',
      sourcePhone: null,
      correction: { correctedName: 'Aïcha Recruteuse' },
    },
  },
  {
    organizationName: 'Acme',
    contact: {
      name: 'John Smith',
      email: 'john@acme.test',
      phone: null,
      sourceName: 'John Smith',
      sourceEmail: 'john@acme.test',
      sourcePhone: null,
      correction: null,
    },
  },
];

describe('filterCrmContacts', () => {
  it('filters corrected and uncorrected contacts', () => {
    expect(filterCrmContacts(contacts, '', 'CORRECTED')).toEqual([contacts[0]]);
    expect(filterCrmContacts(contacts, '', 'UNCORRECTED')).toEqual([contacts[1]]);
  });

  it('searches effective, source and organization values without accents', () => {
    expect(filterCrmContacts(contacts, 'elite', 'ALL')).toEqual([contacts[0]]);
    expect(filterCrmContacts(contacts, 'source@example.test', 'ALL')).toEqual([contacts[0]]);
    expect(filterCrmContacts(contacts, 'john smith', 'ALL')).toEqual([contacts[1]]);
  });

  it('combines text search and correction state', () => {
    expect(filterCrmContacts(contacts, 'acme', 'CORRECTED')).toEqual([]);
    expect(filterCrmContacts(contacts, 'acme', 'UNCORRECTED')).toEqual([contacts[1]]);
  });
});
