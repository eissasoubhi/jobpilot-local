'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const links = [
  ['/', 'Tableau de bord', '⌂'], ['/offres', 'Offres', '◎'], ['/candidatures', 'Candidatures', '✓'],
  ['/positionnements', 'Positionnements', '⇄'], ['/messages', 'Messagerie', '✉'], ['/cv', 'Mes CV', '▤'],
  ['/profil', 'Profil', '◉'], ['/parametres', 'Paramètres', '⚙'],
];

export function Shell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  return <div className="app-shell">
    <aside className="sidebar">
      <div className="brand"><span className="brand-mark">JP</span><div><strong>JobPilot</strong><small>Local</small></div></div>
      <nav>{links.map(([href,label,icon]) => <Link key={href} href={href} className={pathname === href ? 'active' : ''}><span>{icon}</span>{label}</Link>)}</nav>
      <div className="local-badge">● Données locales</div>
    </aside>
    <main className="main">{children}</main>
  </div>;
}
