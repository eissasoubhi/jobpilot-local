import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Button, Empty, ErrorBox, FormField } from '@/components/UI';

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

  it('announces empty states without interrupting the user', () => {
    render(<Empty>Aucun résultat ne correspond aux critères.</Empty>);

    const emptyState = screen.getByRole('status');
    expect(emptyState).toHaveTextContent('Aucun résultat ne correspond aux critères.');
    expect(emptyState).toHaveAttribute('aria-live', 'polite');
  });

  it('explains recoverable errors and exposes diagnostics on demand', () => {
    const retry = vi.fn();

    render(
      <ErrorBox
        title="Synchronisation indisponible"
        message="Les nouvelles offres n’ont pas pu être chargées."
        impact="Les offres déjà enregistrées restent disponibles."
        details="Le service local n’a pas répondu."
        onRetry={retry}
      />,
    );

    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Synchronisation indisponible');
    expect(alert).toHaveTextContent('Les nouvelles offres n’ont pas pu être chargées.');
    expect(alert).toHaveTextContent('Les offres déjà enregistrées restent disponibles.');
    expect(screen.getByText('Voir le diagnostic')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(retry).toHaveBeenCalledTimes(1);
  });

  it('announces skeleton groups once while keeping decorative blocks hidden', () => {
    const { container } = render(
      <SkeletonGroup label="Chargement des indicateurs">
        <Skeleton width="50%" />
        <Skeleton height={24} />
      </SkeletonGroup>,
    );

    const loadingState = screen.getByRole('status', { name: 'Chargement des indicateurs' });
    expect(loadingState).toHaveAttribute('aria-busy', 'true');
    expect(loadingState).toHaveAttribute('aria-live', 'polite');
    expect(container.querySelectorAll('[aria-hidden="true"]')).toHaveLength(2);
  });
});