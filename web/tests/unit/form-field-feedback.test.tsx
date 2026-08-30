import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FormField } from '@/components/UI';

describe('FormField feedback', () => {
  it('associates hint and success feedback with the control', () => {
    render(
      <FormField label="Adresse e-mail" hint="Utilisée pour les alertes" success="Adresse validée">
        <input />
      </FormField>,
    );

    const input = screen.getByLabelText('Adresse e-mail');
    const hint = screen.getByText('Utilisée pour les alertes');
    const success = screen.getByRole('status', { name: '' });

    expect(success).toHaveTextContent('Adresse validée');
    expect(input).toHaveAttribute('aria-describedby', `${hint.id} ${success.id}`);
    expect(input).not.toHaveAttribute('aria-invalid');
  });

  it('prioritizes error feedback over success feedback', () => {
    render(
      <FormField
        label="Adresse e-mail"
        hint="Utilisée pour les alertes"
        error="Adresse invalide"
        success="Adresse validée"
      >
        <input aria-describedby="external-help" />
      </FormField>,
    );

    const input = screen.getByLabelText('Adresse e-mail');
    const hint = screen.getByText('Utilisée pour les alertes');
    const error = screen.getByRole('alert');

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-describedby', `external-help ${hint.id} ${error.id}`);
    expect(screen.queryByText('Adresse validée')).not.toBeInTheDocument();
  });
});
