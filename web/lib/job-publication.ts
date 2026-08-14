export type OfferPublicationTiming = {
  label: string;
  exactLabel: string | null;
  stale: boolean;
};

function elapsedLabel(date: Date, now: Date): string {
  const elapsedMs = Math.max(0, now.getTime() - date.getTime());
  const minutes = Math.floor(elapsedMs / 60_000);

  if (minutes < 1) return 'à l’instant';
  if (minutes < 60) return `il y a ${minutes} min`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `il y a ${hours} h`;

  const days = Math.floor(hours / 24);
  if (days < 14) return `il y a ${days} jour${days > 1 ? 's' : ''}`;

  const weeks = Math.floor(days / 7);
  if (days < 60) return `il y a ${weeks} semaine${weeks > 1 ? 's' : ''}`;

  const months = Math.floor(days / 30);
  if (days < 365) return `il y a ${months} mois`;

  const years = Math.floor(days / 365);
  return `il y a ${years} an${years > 1 ? 's' : ''}`;
}

function exactDateLabel(prefix: string, date: Date): string {
  return `${prefix} ${new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)}`;
}

export function offerPublicationTiming(
  publishedAt?: string | null,
  discoveredAt?: string | null,
  now = new Date(),
): OfferPublicationTiming {
  if (publishedAt) {
    const published = new Date(publishedAt);
    if (!Number.isNaN(published.getTime())) {
      return {
        label: `Publiée ${elapsedLabel(published, now)}`,
        exactLabel: exactDateLabel('Publiée le', published),
        stale: now.getTime() - published.getTime() >= 7 * 24 * 60 * 60 * 1000,
      };
    }
  }

  if (discoveredAt) {
    const discovered = new Date(discoveredAt);
    if (!Number.isNaN(discovered.getTime())) {
      return {
        label: `Détectée ${elapsedLabel(discovered, now)}`,
        exactLabel: exactDateLabel('Détectée le', discovered),
        stale: false,
      };
    }
  }

  return {
    label: 'Publication inconnue',
    exactLabel: null,
    stale: false,
  };
}
