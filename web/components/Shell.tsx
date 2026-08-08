'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const links = [
  ['/', 'Tableau de bord', '⌂'], ['/offres', 'Offres', '◎'], ['/offres/review', 'Review Queue', '▶'],
  ['/connecteurs', 'Connecteurs', '⛓'], ['/criteres-recherche', 'Critères de recherche', '⌕'],
  ['/candidatures', 'Candidatures', '✓'], ['/parcours-candidatures', 'Parcours candidatures', '↝'],
  ['/reporting', 'Reporting', '▥'],
  ['/crm', 'CRM', '◇'], ['/crm/contacts', 'Contacts CRM', '♙'],
  ['/crm/follow-ups', 'Relances CRM', '◷'], ['/reporting/sources', 'Conversion par source', '▥'],
  ['/positionnements', 'Positionnements', '⇄'], ['/messages', 'Messagerie', '✉'],
  ['/cv', 'Mes CV', '▤'], ['/profil', 'Profil', '◉'], ['/parametres', 'Paramètres', '⚙'],
];

export function Shell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">JP</span>
          <div><strong>JobPilot</strong><small>Local</small></div>
        </div>
        <nav>
          {links.map(([href, label, icon]) => (
            <Link key={href} href={href} className={pathname === href ? 'active' : ''}>
              <span>{icon}</span>{label}
            </Link>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="local-badge">● Données locales</div>
          <div className="job-source-links" aria-label="Sources des offres">
            <a href="https://www.arbeitnow.com" target="_blank" rel="noreferrer">Jobs by Arbeitnow</a>
            <a href="https://www.adzuna.fr" target="_blank" rel="noreferrer">Jobs by Adzuna</a>
          </div>
        </div>
      </aside>
      <main className="main">{children}</main>
    </div>
  );
}
