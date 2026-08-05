import type {
  CrmContactRole,
  CrmOrganization,
  CrmOrganizationRole,
} from '@/lib/types';

export type CrmRoleFilter = 'ALL' | CrmOrganizationRole;

const organizationRoleLabels: Record<CrmOrganizationRole, string> = {
  COMPANY: 'Entreprise',
  AGENCY: 'Intermédiaire',
  CLIENT: 'Client final',
};

const contactRoleLabels: Record<CrmContactRole, string> = {
  RECRUITER: 'Recruteur',
  APPLICATION_ADDRESS: 'Adresse de candidature',
  INBOX_CONTACT: 'Correspondant Gmail',
};

export function crmOrganizationRoleLabel(role: CrmOrganizationRole): string {
  return organizationRoleLabels[role];
}

export function crmContactRoleLabel(role: CrmContactRole): string {
  return contactRoleLabels[role];
}

export function normalizeCrmSearch(value: string): string {
  return value
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('fr')
    .replace(/\s+/g, ' ')
    .trim();
}

export function filterCrmOrganizations(
  organizations: readonly CrmOrganization[],
  query: string,
  role: CrmRoleFilter,
): CrmOrganization[] {
  const normalizedQuery = normalizeCrmSearch(query);

  return organizations.filter((organization) => {
    if (role !== 'ALL' && !organization.roles.includes(role)) {
      return false;
    }

    if (normalizedQuery === '') {
      return true;
    }

    const searchableValues = [
      organization.name,
      ...organization.contacts.flatMap((contact) => [
        contact.name ?? '',
        contact.email ?? '',
        contact.phone ?? '',
      ]),
      ...organization.latestOffers.map((offer) => offer.title),
    ];

    return searchableValues.some((value) => normalizeCrmSearch(value).includes(normalizedQuery));
  });
}
