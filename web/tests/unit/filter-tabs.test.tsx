import { createEvent, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { FilterTabs } from '@/components/FilterTabs';

describe('FilterTabs', () => {
  it('exposes the selected filter as an exclusive radio group and forwards changes', () => {
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

    const group = screen.getByRole('radiogroup', { name: 'Filtres des offres' });
    expect(group).toBeInTheDocument();

    const all = screen.getByRole('radio', { name: 'Toutes' });
    const matched = screen.getByRole('radio', { name: 'À examiner' });

    expect(all).toHaveAttribute('aria-checked', 'true');
    expect(all).toHaveAttribute('tabindex', '0');
    expect(matched).toHaveAttribute('aria-checked', 'false');
    expect(matched).toHaveAttribute('tabindex', '-1');

    fireEvent.click(matched);
    expect(onChange).toHaveBeenCalledWith('matched');
  });

  it('supports arrow, Home, and End radio keyboard navigation', () => {
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

    const all = screen.getByRole('radio', { name: 'Toutes' });
    const matched = screen.getByRole('radio', { name: 'À examiner' });
    const excluded = screen.getByRole('radio', { name: 'Exclues' });

    all.focus();
    fireEvent.keyDown(all, { key: 'ArrowRight' });
    expect(onChange).toHaveBeenLastCalledWith('matched');
    expect(matched).toHaveFocus();

    fireEvent.keyDown(matched, { key: 'ArrowDown' });
    expect(onChange).toHaveBeenLastCalledWith('excluded');
    expect(excluded).toHaveFocus();

    fireEvent.keyDown(excluded, { key: 'Home' });
    expect(onChange).toHaveBeenLastCalledWith('all');
    expect(all).toHaveFocus();

    fireEvent.keyDown(all, { key: 'ArrowUp' });
    expect(onChange).toHaveBeenLastCalledWith('excluded');
    expect(excluded).toHaveFocus();

    fireEvent.keyDown(excluded, { key: 'End' });
    expect(excluded).toHaveFocus();
  });

  it('prevents Home and End from scrolling when focus is already at the boundary', () => {
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

    const all = screen.getByRole('radio', { name: 'Toutes' });
    all.focus();
    const home = createEvent.keyDown(all, { key: 'Home' });
    fireEvent(all, home);

    expect(home.defaultPrevented).toBe(true);
    expect(all).toHaveFocus();
    expect(onChange).not.toHaveBeenCalled();

    const excluded = screen.getByRole('radio', { name: 'Exclues' });
    excluded.focus();
    const end = createEvent.keyDown(excluded, { key: 'End' });
    fireEvent(excluded, end);

    expect(end.defaultPrevented).toBe(true);
    expect(excluded).toHaveFocus();
    expect(onChange).not.toHaveBeenCalled();
  });
});
