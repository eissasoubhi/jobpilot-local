export type CrmContactFilter = 'ALL' | 'CORRECTED' | 'UNCORRECTED';

export type FilterableCrmContact = {
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  sourceName?: string | null;
  sourceEmail?: string | null;
  sourcePhone?: string | null;
  correction?: unknown | null;
};

export type FilterableCrmContactEntry = {
  organizationName: string;
  contact: FilterableCrmContact;
};

function normalizedSearchText(entry: FilterableCrmContactEntry): string {
  return [
    entry.organizationName,
    entry.contact.name,
    entry.contact.email,
    entry.contact.phone,
    entry.contact.sourceName,
    entry.contact.sourceEmail,
    entry.contact.sourcePhone,
  ]
    .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
    .join(' ')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('fr');
}

export function filterCrmContacts<TEntry extends FilterableCrmContactEntry>(
  entries: TEntry[],
  search: string,
  filter: CrmContactFilter,
): TEntry[] {
  const normalizedSearch = search
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('fr');

  return entries.filter((entry) => {
    const corrected = entry.contact.correction != null;
    if (filter === 'CORRECTED' && !corrected) return false;
    if (filter === 'UNCORRECTED' && corrected) return false;

    return normalizedSearch === '' || normalizedSearchText(entry).includes(normalizedSearch);
  });
}
