import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/UI';

describe('Button', () => {
  it('defaults to a non-submitting button and forwards interaction props', () => {
    const onClick = vi.fn();

    render(<Button onClick={onClick}>Continuer</Button>);

    const button = screen.getByRole('button', { name: 'Continuer' });
    expect(button).toHaveAttribute('type', 'button');
    fireEvent.click(button);
    expect(onClick).toHaveBeenCalledOnce();
  });

  it('exposes loading state accessibly and blocks duplicate actions', () => {
    const onClick = vi.fn();

    render(<Button loading onClick={onClick}>Enregistrer</Button>);

    const button = screen.getByRole('button', { name: 'Enregistrer' });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
    fireEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
  });

  it('allows explicit submit semantics when a form requires them', () => {
    render(<Button type="submit" variant="danger" size="small">Supprimer</Button>);

    expect(screen.getByRole('button', { name: 'Supprimer' })).toHaveAttribute('type', 'submit');
  });
});
