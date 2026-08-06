import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  GlobalSearchCriteriaPanel,
  parseGlobalCriteriaLines,
} from '@/components/GlobalSearchCriteriaPanel';

const settings = {
  interfaceLanguage: 'fr',
  targetJobs: ['Senior Symfony Developer', 'Backend PHP/Symfony'],
  exclusions: ['Stage'],
  skills: ['PHP', 'Symfony'],
  matchingThreshold: 50,
  defaultIdfTjm: 500,
  defaultOutsideIdfTjm: 480,
  defaultRemoteTjm: 480,
  minimumFreelanceTjm: 300,
  maximumTjm: 520,
  minimumCdiSalary: 35000,
  salaryIncludesTotalCompensation: true,
  cddSalaryRule: null,
  autoPrepare: true,
  autoSubmitEnabled: false,
  autoSubmitThreshold: 60,
  autoSubmitDailyLimit: 5,
  finalSubmissionMode: 'ONE_CLICK',
};

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('GlobalSearchCriteriaPanel', () => {
  it('normalizes duplicate lines while preserving the first casing', () => {
    expect(parseGlobalCriteriaLines(' PHP \nphp\n Symfony 6 \n\nReact')).toEqual([
      'PHP',
      'Symfony 6',
      'React',
    ]);
  });

  it('shows every active key and saves collection and local matching criteria', async () => {
    const updated = {
      ...settings,
      targetJobs: ['Full-Stack Symfony/React'],
      skills: ['PHP', 'React'],
      exclusions: ['Stage', 'Alternance'],
      matchingThreshold: 65,
    };
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => settings,
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => updated,
      });
    vi.stubGlobal('fetch', fetchMock);

    render(<GlobalSearchCriteriaPanel />);

    expect(await screen.findByRole('heading', { name: 'Clés réellement utilisées' })).toBeInTheDocument();
    expect(screen.getAllByText('targetJobs', { selector: 'code' })).toHaveLength(2);
    expect(screen.getAllByText('skills', { selector: 'code' })).toHaveLength(2);
    expect(screen.getByText('exclusions', { selector: 'code' })).toBeInTheDocument();
    expect(screen.getByText('matchingThreshold', { selector: 'code' })).toBeInTheDocument();
    expect(screen.getAllByText('Transmis aux connecteurs')).toHaveLength(2);
    expect(screen.getAllByText('Traitement local')).toHaveLength(2);
    expect(screen.getByText(/contrats acceptés, mobilité, ville et préférence de télétravail/i)).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Postes ciblés globaux — un par ligne'), {
      target: { value: ' Full-Stack Symfony/React \nfull-stack symfony/react' },
    });
    fireEvent.change(screen.getByLabelText('Compétences globales — une par ligne'), {
      target: { value: 'PHP\nReact\nphp' },
    });
    fireEvent.change(screen.getByLabelText('Exclusions locales — une par ligne'), {
      target: { value: 'Stage\nAlternance\nstage' },
    });
    fireEvent.change(screen.getByLabelText('Seuil de préparation automatique'), {
      target: { value: '65' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les critères globaux' }));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    const request = fetchMock.mock.calls[1]?.[1] as RequestInit;
    expect(request.method).toBe('PUT');
    expect(JSON.parse(String(request.body))).toEqual({
      targetJobs: ['Full-Stack Symfony/React'],
      skills: ['PHP', 'React'],
      exclusions: ['Stage', 'Alternance'],
      matchingThreshold: 65,
    });
    expect(await screen.findByText(/Les critères globaux ont été enregistrés/)).toBeInTheDocument();
  });
});
