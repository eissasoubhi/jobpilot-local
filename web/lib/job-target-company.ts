import type { Job } from '@/lib/types';

function normalize(value: string | null | undefined): string {
  return (value ?? '')
    .trim()
    .toLocaleLowerCase('fr-FR')
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();
}

function compact(value: string): string {
  return normalize(value).replace(/[^a-z0-9]+/g, '');
}

function looksLikeSourcePlatform(candidate: string, job: Job): boolean {
  const normalizedCandidate = normalize(candidate);
  if (!normalizedCandidate) return false;

  const normalizedSource = normalize(job.source);
  if (normalizedSource && normalizedCandidate === normalizedSource) return true;

  const normalizedSourceCode = normalize(job.sourceCode);
  if (normalizedCandidate.length >= 4
    && normalizedSourceCode
    && (normalizedSourceCode.includes(normalizedCandidate)
      || normalizedCandidate.includes(normalizedSourceCode))) {
    return true;
  }

  if (job.sourceUrl) {
    try {
      const hostname = new URL(job.sourceUrl).hostname.toLocaleLowerCase('en-US');
      const compactCandidate = compact(candidate);
      const compactHost = hostname.replace(/[^a-z0-9]+/g, '');
      if (compactCandidate.length >= 4 && compactHost.includes(compactCandidate)) return true;
    } catch {
      // Ignore malformed legacy URLs; source/sourceCode checks above still apply.
    }
  }

  return false;
}

/**
 * Returns the company name that is safe to reuse in candidate-facing content.
 * A job board/source name is deliberately treated as unknown rather than as the
 * employer, so the user can provide the real company before regenerating text.
 */
export function jobTargetCompany(job: Job): string {
  const clientName = job.clientName?.trim() ?? '';
  if (clientName && !looksLikeSourcePlatform(clientName, job)) return clientName;

  const company = job.company?.trim() ?? '';
  if (company && !looksLikeSourcePlatform(company, job)) return company;

  return '';
}

export function targetCompanyMissingHint(job: Job): string {
  const platform = job.source?.trim() || job.company?.trim() || 'la plateforme source';
  return `Entreprise non identifiée. ${platform} ne sera pas utilisé comme nom d’entreprise : renseigne le nom réel pour personnaliser la génération.`;
}
