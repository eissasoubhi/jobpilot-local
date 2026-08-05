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

function phoneSearchVariants(value: string): string[] {
  const digits = value.replace(/\D/g, '');
  if (digits.length < 4) {
    return [];
  }

  const variants = new Set([digits]);
  if (digits.startsWith('0033') && digits.length > 4) {
    variants.add(`0${digits.slice(4)}`);
  } else if (digits.startsWith('33') && digits.length > 2) {
    variants.add(`0${digits.slice(2)}`);
  } else if (digits.startsWith('0') && digits.length > 1) {
    variants.add(`33${digits.slice(1)}`);
  }

  return [...variants];
}

function organizationMatchesQuery(organization: CrmOrganization, query: string): boolean {
  const normalizedQuery = normalizeCrmSearch(query);
  if (normalizedQuery === '') {
    return true;
  }

  const searchableValues = [
    organization.name,
    organization.sourceName,
    organization.annotation?.note ?? '',
    ...organization.contacts.flatMap((contact) => [
      contact.name ?? '',
      contact.email ?? '',
    ]),
    ...organization.latestOffers.map((offer) => offer.title),
  ];
  if (searchableValues.some((value) => normalizeCrmSearch(value).includes(normalizedQuery))) {
    return true;
  }

  const queryPhoneVariants = phoneSearchVariants(query);
  if (queryPhoneVariants.length === 0) {
    return false;
  }

  return organization.contacts.some((contact) => {
    if (!contact.phone) return false;

    const contactVariants = phoneSearchVariants(contact.phone);
    return queryPhoneVariants.some((queryVariant) => (
      contactVariants.some((contactVariant) => contactVariant.includes(queryVariant))
    ));
  });
}

export function filterCrmOrganizations(
  organizations: readonly CrmOrganization[],
  query: string,
  role: CrmRoleFilter,
): CrmOrganization[] {
  return organizations.filter((organization) => {
    if (role !== 'ALL' && !organization.roles.includes(role)) {
      return false;
    }

    return organizationMatchesQuery(organization, query);
  });
}
