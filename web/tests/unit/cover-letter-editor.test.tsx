import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { CoverLetterEditor } from '@/components/CoverLetterEditor';

describe('CoverLetterEditor', () => {
  it('explains why an empty generated letter is hidden', () => {
    render(<CoverLetterEditor value="" onChange={vi.fn()} onCopy={vi.fn()} />);

    expect(screen.getByText('Aucune lettre demandée par l’offre.')).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Lettre de motivation' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Copier la lettre' })).not.toBeInTheDocument();
  });

  it('allows a deliberate manual override', () => {
    const onChange = vi.fn();
    render(<CoverLetterEditor value="" onChange={onChange} onCopy={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Ajouter une lettre manuellement' }));

    const editor = screen.getByRole('textbox', { name: 'Lettre de motivation' });
    expect(editor).toBeInTheDocument();
    expect(screen.getByText('Ajout manuel : l’offre ne demandait pas de lettre.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copier la lettre' })).toBeDisabled();

    fireEvent.change(editor, { target: { value: 'Ma lettre manuelle' } });
    expect(onChange).toHaveBeenCalledWith('Ma lettre manuelle');
  });

  it('shows and copies a requested cover letter', () => {
    const onCopy = vi.fn();
    render(
      <CoverLetterEditor
        value="Lettre préparée séparément"
        onChange={vi.fn()}
        onCopy={onCopy}
      />,
    );

    expect(screen.getByRole('textbox', { name: 'Lettre de motivation' })).toHaveValue('Lettre préparée séparément');
    fireEvent.click(screen.getByRole('button', { name: 'Copier la lettre' }));
    expect(onCopy).toHaveBeenCalledWith('Lettre préparée séparément');
  });
});
