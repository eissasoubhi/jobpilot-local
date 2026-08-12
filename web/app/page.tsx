'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';

import { MarketSkillsCard } from '@/components/dashboard/MarketSkillsCard';
import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Job } from '@/lib/types';

import styles from './dashboard.module.css';

type PeriodComparison = {
  current: number;
  previous: number;
  deltaPercent: number | null;
};

type DashboardData = {
  period: {
    days: number;
    from: string;
    to: string;
  };
  comparison: {
    newJobs: PeriodComparison;
    submitted: PeriodComparison;
  };
  counts: {
    jobs: number;
    newJobs: number;
    qualifiedJobs: number;
    applications: number;
    prepared: number;
    submitted: number;
    submittedRecently: number;
    interviews: number;
    rejected: number;
    positionings: number;
    messages: number;
    actionMessages: number;
    followUpsDue: number;
    missingCv: number;
    failedSubmissions: number;
  };
  performance: {
    responseRate: number;
    interviewRate: number;
    responses: number;
    averageScore: number;
    firstResponseMedianHours: number | null;
    firstResponseMeasured: number;
  };
  sourcePerformance: {
    trackedSources: number;
    leaders: Array<{
      code: string;
      name: string;
      submitted: number;
      responses: number;
      interviews: number;
      responseRate: number;
      interviewRate: number;
      averageMatchingScore: number;
      lowVolume: boolean;
    }>;
  };
  pipeline: Array<{
    key: string;
    label: string;
    value: number;
  }>;
  trend: Array<{
    date: string;
    jobs: number;
    submitted: number;
  }>;
  automation: {
    matchingThreshold: number;
    autoPrepare: boolean;
    autoSubmitEnabled: boolean;
    autoSubmitThreshold: number;
    autoSubmitDailyLimit: number;
    targetJobsCount: number;
  };
  connectors: {
    total: number;
    operational: number;
    needsAttention: number;
    lastSyncedAt: string | null;
  };
  recentJobs: Job[];
};

type AttentionItem = {
  label: string;
  description: string;
  count: number;
  href: string;
  action: string;
  priority: 'primary' | 'warning' | 'neutral';
};

const quickLinks = [
  ['/offres/review', 'Review Queue', 'Décider rapidement sur les offres prêtes'],
  ['/criteres-recherche', 'Critères de recherche', 'Ajuster les postes, filtres et exclusions'],
  ['/connecteurs', 'Connecteurs', 'Contrôler les sources et synchronisations'],
  ['/parametres/integrations', 'Clés API & IA', 'Gérer Gemini et les intégrations'],
  ['/parametres/scraping', 'Scraping', 'Configurer les sources personnalisées'],
  ['/reporting', 'Reporting', 'Analyser les conversions en détail'],
  ['/profil', 'Profil', 'Mettre à jour les données candidat'],
  ['/cv', 'CV', 'Contrôler les versions de CV disponibles'],
] as const;

function formatRate(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
}

function formatDay(value: string): string {
  return new Intl.DateTimeFormat('fr-FR', { weekday: 'short' }).format(new Date(`${value}T12:00:00`));
}

function formatDateTime(value: string | null): string {
  if (value === null) return 'Jamais';
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatResponseDelay(hours: number | null): string {
  if (hours === null) return '—';

  if (hours < 24) {
    return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(hours)} h`;
  }

  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(hours / 24)} j`;
}

function formatComparison(comparison: PeriodComparison): string {
  if (comparison.deltaPercent === null) {
    return 'nouveau vs 7 j précédents';
  }

  if (comparison.deltaPercent === 0) {
    return 'stable vs 7 j précédents';
  }

  const value = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(comparison.deltaPercent);
  return `${comparison.deltaPercent > 0 ? '+' : ''}${value} % vs 7 j précédents`;
}

function KpiCard({
  label,
  value,
  note,
  href,
  emphasis = false,
}: {
  label: string;
  value: string | number;
  note: string;
  href: string;
  emphasis?: boolean;
}) {
  return (
    <Link className={`${styles.kpiCard} ${emphasis ? styles.kpiEmphasis : ''}`} href={href}>
      <span className={styles.kpiLabel}>{label}</span>
      <strong>{value}</strong>
      <span className={styles.kpiNote}>{note}</span>
    </Link>
  );
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<DashboardData>('/dashboard')
      .then((result) => {
        if (active) {
          setData(result);
          setError('');
        }
      })
      .catch((caughtError: unknown) => {
        if (active) {
          setError(getErrorMessage(caughtError));
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const attentionItems = useMemo<AttentionItem[]>(() => {
    if (data === null) return [];

    return [
      {
        label: 'Messages à traiter',
        description: 'Réponses détectées qui nécessitent une action de ta part.',
        count: data.counts.actionMessages,
        href: '/messages',
        action: 'Traiter les messages',
        priority: 'warning',
      },
      {
        label: 'Relances dues',
        description: 'Relances CRM arrivées à échéance et toujours ouvertes.',
        count: data.counts.followUpsDue,
        href: '/crm/follow-ups',
        action: 'Faire les relances',
        priority: 'warning',
      },
      {
        label: 'Offres à revoir',
        description: 'Des candidatures sont prêtes : valider ou écarter les offres.',
        count: data.counts.prepared,
        href: '/offres/review',
        action: 'Ouvrir la Review Queue',
        priority: 'primary',
      },
      {
        label: 'Candidatures bloquées',
        description: 'CV manquant ou tentative d’envoi en échec.',
        count: data.counts.missingCv + data.counts.failedSubmissions,
        href: '/candidatures',
        action: 'Résoudre les blocages',
        priority: 'neutral',
      },
      {
        label: 'Connecteurs à vérifier',
        description: 'Sources activées mais non opérationnelles ou incomplètement configurées.',
        count: data.connectors.needsAttention,
        href: '/connecteurs',
        action: 'Vérifier les sources',
        priority: 'neutral',
      },
    ];
  }, [data]);

  if (error !== '') {
    return (
      <>
        <PageHeader title="Tableau de bord" />
        <ErrorBox message={error} />
      </>
    );
  }

  if (data === null) {
    return <Loading />;
  }

  const maxTrend = Math.max(1, ...data.trend.flatMap((item) => [item.jobs, item.submitted]));
  const maxPipeline = Math.max(1, ...data.pipeline.map((item) => item.value));
  const pendingAttention = attentionItems.reduce((total, item) => total + item.count, 0);
  const focusItem = attentionItems.find((item) => item.count > 0) ?? null;

  return (
    <div className={styles.dashboard}>
      <PageHeader
        title="Tableau de bord"
        description="L’essentiel pour savoir où tu en es, ce qui évolue et quoi faire maintenant."
        actions={focusItem !== null
          ? <Link className="btn" href={focusItem.href}>{focusItem.action}</Link>
          : <Link className="btn" href="/offres/review">Ouvrir la Review Queue</Link>}
      />

      <section className={styles.kpiGrid} aria-label="Indicateurs principaux">
        <KpiCard
          label="Nouvelles offres"
          value={data.counts.newJobs}
          note={formatComparison(data.comparison.newJobs)}
          href="/offres"
        />
        <KpiCard
          label="À revoir"
          value={data.counts.prepared}
          note="candidatures prêtes à décider"
          href="/offres/review"
          emphasis={data.counts.prepared > 0}
        />
        <KpiCard
          label="Envoyées"
          value={data.counts.submittedRecently}
          note={formatComparison(data.comparison.submitted)}
          href="/candidatures"
        />
        <KpiCard
          label="Taux de réponse"
          value={formatRate(data.performance.responseRate)}
          note={`${data.performance.responses} réponse(s) enregistrée(s)`}
          href="/reporting"
        />
      </section>

      <section className={styles.primaryGrid}>
        <Card className={styles.attentionCard}>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>Focus du jour</span>
              <h2>À faire maintenant</h2>
            </div>
            <Badge tone={pendingAttention > 0 ? 'warn' : 'good'}>
              {pendingAttention > 0 ? `${pendingAttention} action(s)` : 'À jour'}
            </Badge>
          </div>

          {focusItem !== null && (
            <p className={styles.contextNote}>
              Priorité recommandée : <strong>{focusItem.label}</strong>. Les réponses et relances passent avant la revue des nouvelles candidatures.
            </p>
          )}

          {pendingAttention === 0 ? (
            <div className={styles.clearState}>
              <strong>Rien d’urgent.</strong>
              <span>La Review Queue, les messages, les relances et les sources ne demandent aucune action.</span>
            </div>
          ) : (
            <div className={styles.attentionList}>
              {attentionItems.filter((item) => item.count > 0).map((item) => {
                const priorityClass = item.priority === 'primary'
                  ? styles.primary
                  : item.priority === 'warning'
                    ? styles.warning
                    : '';

                return (
                  <Link className={styles.attentionRow} href={item.href} key={item.label}>
                    <span className={`${styles.attentionCount} ${priorityClass}`}>{item.count}</span>
                    <span className={styles.attentionCopy}>
                      <strong>{item.label}</strong>
                      <small>{item.description}</small>
                    </span>
                    <span className={styles.rowAction}>{item.action} →</span>
                  </Link>
                );
              })}
            </div>
          )}
        </Card>

        <Card className={styles.activityCard}>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>7 jours</span>
              <h2>Activité récente</h2>
            </div>
            <div className={styles.legend} aria-label="Légende du graphique">
              <span><i className={styles.legendJobs} />Offres</span>
              <span><i className={styles.legendSubmitted} />Envoyées</span>
            </div>
          </div>

          <div
            className={styles.activityChart}
            role="img"
            aria-label="Évolution des offres détectées et candidatures envoyées sur les sept derniers jours"
          >
            {data.trend.map((item) => (
              <div className={styles.activityDay} key={item.date}>
                <div className={styles.barArea}>
                  <span
                    className={styles.jobBar}
                    style={{ height: `${item.jobs === 0 ? 3 : Math.max(10, (item.jobs / maxTrend) * 100)}%` }}
                    title={`${item.jobs} offre(s)`}
                  />
                  <span
                    className={styles.submittedBar}
                    style={{ height: `${item.submitted === 0 ? 3 : Math.max(10, (item.submitted / maxTrend) * 100)}%` }}
                    title={`${item.submitted} candidature(s) envoyée(s)`}
                  />
                </div>
                <strong>{formatDay(item.date)}</strong>
                <small>{item.jobs} / {item.submitted}</small>
              </div>
            ))}
          </div>
        </Card>
      </section>

      <section className={styles.analyticsGrid}>
        <Card>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>Conversion</span>
              <h2>Parcours global</h2>
            </div>
            <Link className={styles.textLink} href="/reporting">Reporting détaillé →</Link>
          </div>
          <div className={styles.pipeline}>
            {data.pipeline.map((item) => (
              <div className={styles.pipelineRow} key={item.key}>
                <div className={styles.pipelineMeta}>
                  <span>{item.label}</span>
                  <strong>{item.value}</strong>
                </div>
                <div className={styles.pipelineTrack}>
                  <span style={{ width: `${Math.max(item.value > 0 ? 3 : 0, (item.value / maxPipeline) * 100)}%` }} />
                </div>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>Qualité</span>
              <h2>Performance</h2>
            </div>
          </div>
          <div className={styles.performanceGrid}>
            <div><span>Taux de réponse</span><strong>{formatRate(data.performance.responseRate)}</strong></div>
            <div><span>Conversion entretien</span><strong>{formatRate(data.performance.interviewRate)}</strong></div>
            <div><span>Délai médian 1re réponse</span><strong>{formatResponseDelay(data.performance.firstResponseMedianHours)}</strong></div>
            <div><span>Score moyen</span><strong>{data.performance.averageScore}/100</strong></div>
          </div>
          <p className={styles.contextNote}>
            Le délai de première réponse est calculé sur {data.performance.firstResponseMeasured} candidature(s) avec une réponse Gmail liée, à partir de la date d’envoi. Les autres indicateurs utilisent uniquement les statuts enregistrés dans JobPilot.
          </p>
        </Card>
      </section>

      <Card>
        <div className={styles.sectionHeading}>
          <div>
            <span className={styles.eyebrow}>Efficacité des canaux</span>
            <h2>Sources qui performent</h2>
          </div>
          <Link className={styles.textLink} href="/reporting/sources">Détail par source →</Link>
        </div>
        {data.sourcePerformance.leaders.length === 0 ? (
          <div className="empty">Pas encore assez de candidatures pour comparer les sources.</div>
        ) : (
          <div className={styles.settingsList}>
            {data.sourcePerformance.leaders.map((source, index) => (
              <div key={source.code}>
                <span>
                  #{index + 1} {source.name}{source.lowVolume ? ' · faible volume' : ''}
                </span>
                <strong>
                  {source.submitted} envoyée(s) · {formatRate(source.responseRate)} réponses · {source.interviews} entretien(s) · score {new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(source.averageMatchingScore)}/100
                </strong>
              </div>
            ))}
          </div>
        )}
        <p className={styles.contextNote}>
          {data.sourcePerformance.trackedSources} source(s) avec candidature(s). Classement par envois, puis réponses et entretiens. « Faible volume » signifie moins de 3 envois ; une offre trouvée sur plusieurs sources peut créditer plusieurs canaux.
        </p>
      </Card>

      <MarketSkillsCard />

      <section className={styles.quickSection}>
        <div className={styles.sectionHeadingOutside}>
          <div>
            <span className={styles.eyebrow}>Accès rapide</span>
            <h2>Actions & configuration</h2>
          </div>
        </div>
        <div className={styles.quickGrid}>
          {quickLinks.map(([href, label, description]) => (
            <Link className={styles.quickLink} href={href} key={href}>
              <strong>{label}</strong>
              <span>{description}</span>
              <i>→</i>
            </Link>
          ))}
        </div>
      </section>

      <section className={styles.bottomGrid}>
        <Card>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>Configuration active</span>
              <h2>Automatisation & sources</h2>
            </div>
            <Link className={styles.textLink} href="/parametres">Modifier →</Link>
          </div>
          <div className={styles.settingsList}>
            <div><span>Seuil de matching</span><strong>{data.automation.matchingThreshold}/100</strong></div>
            <div><span>Préparation automatique</span><Badge tone={data.automation.autoPrepare ? 'good' : 'neutral'}>{data.automation.autoPrepare ? 'Active' : 'Inactive'}</Badge></div>
            <div><span>Envoi automatique</span><Badge tone={data.automation.autoSubmitEnabled ? 'warn' : 'neutral'}>{data.automation.autoSubmitEnabled ? 'Actif' : 'Désactivé'}</Badge></div>
            <div><span>Seuil auto-envoi</span><strong>{data.automation.autoSubmitThreshold}/100</strong></div>
            <div><span>Limite quotidienne</span><strong>{data.automation.autoSubmitDailyLimit}</strong></div>
            <div><span>Postes cibles</span><strong>{data.automation.targetJobsCount}</strong></div>
            <div>
              <span>Connecteurs opérationnels</span>
              <Badge tone={data.connectors.needsAttention > 0 ? 'warn' : 'good'}>
                {data.connectors.operational}/{data.connectors.total}
              </Badge>
            </div>
            <div><span>Dernière synchronisation</span><strong>{formatDateTime(data.connectors.lastSyncedAt)}</strong></div>
          </div>
        </Card>

        <Card>
          <div className={styles.sectionHeading}>
            <div>
              <span className={styles.eyebrow}>Dernières découvertes</span>
              <h2>Offres récentes</h2>
            </div>
            <Link className={styles.textLink} href="/offres">Voir tout →</Link>
          </div>
          {data.recentJobs.length === 0 ? (
            <div className="empty">Aucune offre importée.</div>
          ) : (
            <div className={styles.recentList}>
              {data.recentJobs.map((job) => (
                <Link className={styles.recentRow} href="/offres" key={job.id}>
                  <div>
                    <strong>{job.title}</strong>
                    <span>{job.company || 'Entreprise non renseignée'} · {job.location || 'Lieu non renseigné'}</span>
                  </div>
                  <span className={styles.scorePill}>{job.score}</span>
                </Link>
              ))}
            </div>
          )}
        </Card>
      </section>
    </div>
  );
}
