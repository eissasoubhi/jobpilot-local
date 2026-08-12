import { describe, expect, it } from 'vitest';

import { crmOrganizationHref, crmQueryFromSearch } from '@/lib/crm-navigation';

describe('CRM context navigation', () => {
  it('reads and trims an organization query from the URL', () => {
    expect(crmQueryFromSearch('?q=Acme%20France')).toBe('Acme France');
    expect(crmQueryFromSearch('?role=AGENCY&q=%20Cabinet%20Tech%20')).toBe('Cabinet Tech');
  });

  it('returns an empty query when q is absent', () => {
    expect(crmQueryFromSearch('?role=COMPANY')).toBe('');
    expect(crmQueryFromSearch('')).toBe('');
  });

  it('builds a safe CRM URL from an organization name', () => {
    expect(crmOrganizationHref(' Acme & Partners ')).toBe('/crm?q=Acme%20%26%20Partners');
    expect(crmOrganizationHref('')).toBeNull();
    expect(crmOrganizationHref('   ')).toBeNull();
  });
});
