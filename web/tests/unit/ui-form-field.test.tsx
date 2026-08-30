import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FormField } from '@/components/UI';

describe('FormField', () => {
  it('associates hint and error feedback with the control', () => {
    render(
      <FormField
        label="Adresse e-mail"
        hint="Utilise une adresse professionnelle."
        error="Adresse e-mail invalide."
      >
        <input aria-describedby="existing-description" defaultValue="not-an-email" />
      </FormField>,
    );

    const input = screen.getByRole('textbox', { name: 'Adresse e-mail' });
    const hint = screen.getByText('Utilise une adresse professionnelle.');
    const error = screen.getByRole('alert');
    const describedBy = input.getAttribute('aria-describedby')?.split(' ') ?? [];

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(describedBy).toContain('existing-description');
    expect(describedBy).toContain(hint.id);
    expect(describedBy).toContain(error.id);
    expect(error).toHaveTextContent('Adresse e-mail invalide.');
  });

  it('preserves an existing invalid state when no FormField error is provided', () => {
    render(
      <FormField label="Recherche" hint="Recherche locale uniquement.">
        <input type="search" aria-invalid="grammar" />
      </FormField>,
    );

    const input = screen.getByRole('searchbox', { name: 'Recherche' });
    const hint = screen.getByText('Recherche locale uniquement.');

    expect(input).toHaveAttribute('aria-invalid', 'grammar');
    expect(input.getAttribute('aria-describedby')?.split(' ')).toContain(hint.id);
  });
});
