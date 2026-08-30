import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Button, FormField } from '@/components/UI';

describe('shared UI primitives', () => {
  it('associates FormField labels with nested form controls', () => {
    render(
      <FormField label="Source" hint="Choisissez une source configurée.">
        <select defaultValue="manual">
          <option value="manual">Manuel</option>
        </select>
      </FormField>,
    );

    const control = screen.getByRole('combobox', { name: 'Source' });
    const label = screen.getByText('Source');

    expect(label).toHaveAttribute('for', control.id);
    expect(control).toHaveValue('manual');
    expect(screen.getByText('Choisissez une source configurée.')).toBeInTheDocument();
  });

  it('keeps loading buttons disabled and exposes the busy state', () => {
    render(<Button loading>Enregistrer</Button>);

    const button = screen.getByRole('button', { name: 'Enregistrer' });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
  });
});
