export function crmQueryFromSearch(search: string): string {
  const query = new URLSearchParams(search).get('q');
  return query?.trim() ?? '';
}

export function crmOrganizationHref(organizationName: string): string | null {
  const name = organizationName.trim();
  return name === '' ? null : `/crm?q=${encodeURIComponent(name)}`;
}
