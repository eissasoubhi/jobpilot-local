import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { FilterTabs } from '@/components/FilterTabs';

describe('FilterTabs', () => {
  it('exposes the selected filter and forwards changes', () => {
    const onChange = vi.fn();

    render(
      <FilterTabs
        ariaLabel="Filtres des offres"
        options={[
          { value: 'all', label: 'Toutes' },
          { value: 'matched', label: 'À examiner' },
        ] as const}
        value="all"
        onChange={onChange}
      />,
    );

    const group = screen.getByRole('group', { name: 'Filtres des offres' });
    expect(group).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Toutes' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'À examiner' })).toHaveAttribute('aria-pressed', 'false');

    fireEvent.click(screen.getByRole('button', { name: 'À examiner' }));
    expect(onChange).toHaveBeenCalledWith('matched');
  });

  it('supports arrow, Home, and End keyboard navigation', () => {
    const onChange = vi.fn();

    render(
      <FilterTabs
        ariaLabel="Filtres des offres"
        options={[
          { value: 'all', label: 'Toutes' },
          { value: 'matched', label: 'À examiner' },
          { value: 'excluded', label: 'Exclues' },
        ] as const}
        value="all"
        onChange={onChange}
      />,
    );

    const all = screen.getByRole('button', { name: 'Toutes' });
    const matched = screen.getByRole('button', { name: 'À examiner' });
    const excluded = screen.getByRole('button', { name: 'Exclues' });

    all.focus();
    fireEvent.keyDown(all, { key: 'ArrowRight' });
    expect(onChange).toHaveBeenLastCalledWith('matched');
    expect(matched).toHaveFocus();

    fireEvent.keyDown(matched, { key: 'End' });
    expect(onChange).toHaveBeenLastCalledWith('excluded');
    expect(excluded).toHaveFocus();

    fireEvent.keyDown(excluded, { key: 'Home' });
    expect(onChange).toHaveBeenLastCalledWith('all');
    expect(all).toHaveFocus();

    fireEvent.keyDown(all, { key: 'ArrowLeft' });
    expect(onChange).toHaveBeenLastCalledWith('excluded');
    expect(excluded).toHaveFocus();
  });
});
