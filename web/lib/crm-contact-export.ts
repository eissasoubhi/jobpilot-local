import type { EditableCrmContact } from '@/components/CrmContactCorrectionEditor';
import type { CrmOrganization } from '@/lib/types';

export type CrmContactExportEntry = {
  organization: CrmOrganization;
  organizationName: string;
  contact: EditableCrmContact;
};

const HEADERS = [
  'Organisation',
  'Nom',
  'E-mail',
  'Téléphone',
  'Rôles',
  'Corrigé localement',
  'Nom source',
  'E-mail source',
  'Téléphone source',
  'Dernier contact',
  'Nombre de messages',
];

function spreadsheetSafe(value: string): string {
  const normalized = value.replace(/\r?\n/g, ' ').trim();

  return /^[=+\-@]/.test(normalized) ? `'${normalized}` : normalized;
}

function csvCell(value: unknown): string {
  const safe = spreadsheetSafe(value == null ? '' : String(value));

  return `"${safe.replace(/"/g, '""')}"`;
}

export function createCrmContactsCsv(entries: CrmContactExportEntry[]): string {
  const rows = entries.map(({ organizationName, contact }) => [
    organizationName,
    contact.name ?? '',
    contact.email ?? '',
    contact.phone ?? '',
    contact.roles.join(' | '),
    contact.correction != null ? 'Oui' : 'Non',
    contact.sourceName ?? '',
    contact.sourceEmail ?? '',
    contact.sourcePhone ?? '',
    contact.lastContactAt ?? '',
    contact.messageCount,
  ]);

  return `\uFEFF${[HEADERS, ...rows].map((row) => row.map(csvCell).join(';')).join('\r\n')}\r\n`;
}

export function downloadCrmContactsCsv(entries: CrmContactExportEntry[], filename = 'contacts-crm.csv'): void {
  const blob = new Blob([createCrmContactsCsv(entries)], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
