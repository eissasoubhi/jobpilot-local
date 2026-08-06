import type { CrmContactFilter } from '@/lib/crm-contact-filters';

export type CrmContactFilterState = {
  search: string;
  filter: CrmContactFilter;
};

const allowedFilters = new Set<CrmContactFilter>(['ALL', 'CORRECTED', 'UNCORRECTED']);

export function parseCrmContactFilterState(searchParams: URLSearchParams): CrmContactFilterState {
  const search = (searchParams.get('q') ?? '').trim();
  const candidate = searchParams.get('correction') as CrmContactFilter | null;

  return {
    search,
    filter: candidate !== null && allowedFilters.has(candidate) ? candidate : 'ALL',
  };
}

export function buildCrmContactFilterQuery(state: CrmContactFilterState): string {
  const params = new URLSearchParams();
  const search = state.search.trim();

  if (search !== '') params.set('q', search);
  if (state.filter !== 'ALL') params.set('correction', state.filter);

  const query = params.toString();
  return query === '' ? '' : `?${query}`;
}
