import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DataList, DataListItem } from '@/components/UI';

describe('DataList', () => {
  it('exposes native list semantics while preserving accessible names', () => {
    render(
      <DataList aria-label="Résultats">
        <DataListItem>Premier résultat</DataListItem>
        <DataListItem>Deuxième résultat</DataListItem>
      </DataList>,
    );

    const list = screen.getByRole('list', { name: 'Résultats' });
    const items = within(list).getAllByRole('listitem');

    expect(list.tagName).toBe('UL');
    expect(items).toHaveLength(2);
    expect(items[0].tagName).toBe('LI');
    expect(items[0]).toHaveTextContent('Premier résultat');
    expect(items[1]).toHaveTextContent('Deuxième résultat');
  });
});
