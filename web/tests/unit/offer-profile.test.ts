import { describe, expect, it } from 'vitest';

import { contractKind, matchesProfileContracts } from '@/lib/offer-profile';

describe('offer profile contract filtering', () => {
  it('groups freelance, portage and subcontracting in the freelance family', () => {
    expect(contractKind('Mission freelance')).toBe('freelance');
    expect(contractKind('Portage salarial')).toBe('freelance');
    expect(contractKind('Sous-traitance')).toBe('freelance');
  });

  it('hides CDI when the profile accepts only freelance-family contracts', () => {
    const profile = { acceptedContracts: ['Freelance', 'Portage salarial', 'Sous-traitance'] };

    expect(matchesProfileContracts({ contractType: 'Freelance' }, profile)).toBe(true);
    expect(matchesProfileContracts({ contractType: 'Portage salarial' }, profile)).toBe(true);
    expect(matchesProfileContracts({ contractType: 'CDI' }, profile)).toBe(false);
  });

  it('does not filter contracts when the profile has no contract preference', () => {
    expect(matchesProfileContracts({ contractType: 'CDI' }, { acceptedContracts: [] })).toBe(true);
  });
});
