import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Modal } from '@/components/Modal';

describe('Modal', () => {
  it('exposes dialog semantics and focuses the first interactive control', () => {
    render(
      <Modal ariaLabel="Modifier le suivi" onClose={vi.fn()}>
        <button type="button">Fermer</button>
        <input aria-label="Référence" />
      </Modal>,
    );

    expect(screen.getByRole('dialog', { name: 'Modifier le suivi' })).toHaveAttribute('aria-modal', 'true');
    expect(screen.getByRole('button', { name: 'Fermer' })).toHaveFocus();
  });

  it('closes with Escape and a direct backdrop click', () => {
    const onClose = vi.fn();
    const { container } = render(
      <Modal ariaLabel="Candidature" onClose={onClose}>
        <button type="button">Action</button>
      </Modal>,
    );

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);

    const backdrop = container.firstElementChild as HTMLElement;
    fireEvent.mouseDown(backdrop);
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('keeps Tab navigation inside the dialog', () => {
    render(
      <Modal ariaLabel="Candidature" onClose={vi.fn()}>
        <button type="button">Premier</button>
        <button type="button">Dernier</button>
      </Modal>,
    );

    const first = screen.getByRole('button', { name: 'Premier' });
    const last = screen.getByRole('button', { name: 'Dernier' });

    last.focus();
    fireEvent.keyDown(document, { key: 'Tab' });
    expect(first).toHaveFocus();

    first.focus();
    fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
    expect(last).toHaveFocus();
  });

  it('restores focus to the opener after unmount', () => {
    const opener = document.createElement('button');
    document.body.append(opener);
    opener.focus();

    const { unmount } = render(
      <Modal ariaLabel="Candidature" onClose={vi.fn()}>
        <button type="button">Fermer</button>
      </Modal>,
    );

    unmount();
    expect(opener).toHaveFocus();
    opener.remove();
  });
});
