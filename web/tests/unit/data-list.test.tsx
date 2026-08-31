import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DataList, DataListItem, DataToolbar } from '@/components/UI';

describe('shared data list contracts', () => {
  it('exposes list semantics for dense repeated content', () => {
    render(
      <DataList data-testid="results-list">
        <DataListItem>Premier résultat</DataListItem>
        <DataListItem>Deuxième résultat</DataListItem>
      </DataList>,
    );

    const list = screen.getByTestId('results-list');
    expect(list).toHaveAttribute('role', 'list');
    expect(within(list).getAllByRole('listitem')).toHaveLength(2);
  });

  it('keeps contextual actions adjacent to toolbar content', () => {
    render(
      <DataToolbar actions={<button type="button">Actualiser</button>}>
        <h2>Incidents</h2>
        <p>Erreurs répétées.</p>
      </DataToolbar>,
    );

    expect(screen.getByRole('heading', { name: 'Incidents' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Actualiser' })).toBeInTheDocument();
  });
});
