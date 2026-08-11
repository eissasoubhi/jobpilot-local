'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

import { AiSidebarStatus } from '@/components/AiSidebarStatus';
import { CatalogResetPanel } from '@/components/CatalogResetPanel';
import { GeminiPaidQuotaPresetPanel } from '@/components/GeminiPaidQuotaPresetPanel';
import { ProfileCleanupPanel } from '@/components/ProfileCleanupPanel';

type NavigationLink = readonly [href: string, label: string, icon: string];

type NavigationGroup = {
  label: string;
  links: readonly NavigationLink[];
};

const navigation: readonly NavigationGroup[] = [
  {
    label: 'Travail',
    links: [
      ['/', 'Tableau de bord', '⌂'],
      ['/offres', 'Offres', '◎'],
      ['/offres/review', 'Review Queue', '▶'],
      ['/candidatures', 'Candidatures', '✓'],
    ],
  },
  {
    label: 'Recherche',
    links: [
      ['/connecteurs', 'Connecteurs', '⛓'],
      ['/criteres-recherche', 'Critères de recherche', '⌕'],
    ],
  },
  {
    label: 'CRM & suivi',
    links: [
      ['/parcours-candidatures', 'Parcours candidatures', '↝'],
      ['/positionnements', 'Positionnements', '⇄'],
      ['/messages', 'Messagerie', '✉'],
      ['/crm', 'CRM', '◇'],
      ['/crm/contacts', 'Contacts CRM', '♙'],
      ['/crm/follow-ups', 'Relances CRM', '◷'],
    ],
  },
  {
    label: 'Analyse',
    links: [
      ['/reporting', 'Reporting', '▥'],
      ['/reporting/sources', 'Conversion par source', '▥'],
    ],
  },
  {
    label: 'Configuration',
    links: [
      ['/parametres/integrations', 'Configuration & clés API', '⚙'],
      ['/parametres/scraping', 'Scraping personnalisé', '⌘'],
      ['/cv', 'Mes CV', '▤'],
      ['/profil', 'Profil', '◉'],
      ['/profil/reponses', 'Réponses automatiques', '✎'],
      ['/parametres', 'Paramètres', '☷'],
    ],
  },
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
        <nav className="sidebar-nav" aria-label="Navigation principale">
          {navigation.map((group) => (
            <div className="sidebar-nav-group" key={group.label}>
              <div className="sidebar-nav-title">{group.label}</div>
              <div className="sidebar-nav-links">
                {group.links.map(([href, label, icon]) => (
                  <Link key={href} href={href} className={pathname === href ? 'active' : ''}>
                    <span>{icon}</span>{label}
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </nav>
        <AiSidebarStatus />
        <div className="sidebar-footer">
          <div className="local-badge">● Données locales</div>
          <div className="job-source-links" aria-label="Sources des offres">
            <a href="https://www.arbeitnow.com" target="_blank" rel="noreferrer">Jobs by Arbeitnow</a>
            <a href="https://www.adzuna.fr" target="_blank" rel="noreferrer">Jobs by Adzuna</a>
          </div>
        </div>
      </aside>
      <main className="main">
        {children}
        {pathname === '/parametres/integrations' && <GeminiPaidQuotaPresetPanel />}
        {pathname === '/parametres' && (
          <>
            <ProfileCleanupPanel />
            <CatalogResetPanel />
          </>
        )}
      </main>
    </div>
  );
}
