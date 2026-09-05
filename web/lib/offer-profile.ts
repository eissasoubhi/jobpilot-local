import type { Job, Profile } from '@/lib/types';

type ContractKind = 'cdi' | 'cdd' | 'freelance' | 'other';

function normalize(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

export function contractKind(value: string): ContractKind {
  const normalized = normalize(value);

  if (/\bcdi\b/.test(normalized)) return 'cdi';
  if (/\bcdd\b/.test(normalized)) return 'cdd';
  if (/freelance|independant|independent|mission|portage|sous traitance|non salarie/.test(normalized)) return 'freelance';

  return 'other';
}

export function matchesProfileContracts(
  job: Pick<Job, 'contractType'>,
  profile: Pick<Profile, 'acceptedContracts'>,
): boolean {
  if (profile.acceptedContracts.length === 0) return true;

  const offerKind = contractKind(job.contractType);
  if (offerKind === 'other') {
    const normalizedOffer = normalize(job.contractType);
    return profile.acceptedContracts.some((contract) => normalize(contract) === normalizedOffer);
  }

  return profile.acceptedContracts.some((contract) => contractKind(contract) === offerKind);
}
