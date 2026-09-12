'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';

import {
  Badge,
  Button,
  ButtonLink,
  Card,
  DataList,
  DataListItem,
  Empty,
  ErrorBox,
  FormField,
  InlineFeedback,
  Loading,
  PageHeader,
  ProgressBar,
} from '@/components/UI';
import type { AiUsageEvent, AiUsagePayload, AiUsageSummary } from '@/lib/aiUsage';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './page.module.css';

type CalendarDay = {
  date: string;
  day: number;
  monthKey: string;
  monthLabel: string;
  weekdayOffset?: number;
  summary: AiUsageSummary | null;
};

type CalendarMonth = {
  key: string;
  label: string;
  offset: number;
  days: CalendarDay[];
};

const weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

function number(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

function money(value: number | null, currency: 'USD' | 'EUR'): string {
  if (value === null) return '—';
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency,
    minimumFractionDigits: value > 0 && value < 0.01 ? 4 : 2,
    maximumFractionDigits: value > 0 && value < 0.01 ? 6 : 2,
  }).format(value);
}

function dateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function percent(used: number, limit: number): number {
  if (limit <= 0) return 0;
  return Math.min(100, Math.max(0, Math.round((used / limit) * 100)));
}

function purposeLabel(purpose: string): string {
  return {
    job_match: 'Matching offre ↔ profil',
    application_question: 'Réponse à une question de candidature',
    custom_scraper_extraction: 'Extraction IA d’un connecteur',
  }[purpose] ?? purpose;
}

function outcomeLabel(outcome: string): string {
  return {
    provider_success: 'Gemini appelé · succès',
    provider_failure: 'Gemini appelé · échec',
    cache_hit: 'Cache JobPilot',
    quota_blocked: 'Quota local bloqué',
    quota_error: 'Compteur de quota indisponible',
  }[outcome] ?? outcome;
}

function outcomeTone(outcome: string): 'neutral' | 'good' | 'warn' | 'bad' | 'blue' {
  if (outcome === 'provider_success') return 'good';
  if (outcome === 'cache_hit') return 'blue';
  if (outcome === 'quota_blocked' || outcome === 'quota_error') return 'warn';
  if (outcome === 'provider_failure') return 'bad';
  return 'neutral';
}

function calendarMonths(calendar: AiUsageSummary[]): CalendarMonth[] {
  const summaries = new Map(calendar.filter((item) => item.date).map((item) => [item.date as string, item]));
  const end = new Date();
  end.setHours(12, 0, 0, 0);
  const start = new Date(end);
  start.setDate(start.getDate() - 364);
  const months = new Map<string, CalendarMonth>();

  for (const cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
    const value = new Date(cursor);
    const key = dateKey(value);
    const monthKey = key.slice(0, 7);
    let month = months.get(monthKey);
    if (!month) {
      const first = new Date(value.getFullYear(), value.getMonth(), 1, 12);
      month = {
        key: monthKey,
        label: new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(first),
        offset: (first.getDay() + 6) % 7,
        days: [],
      };
      months.set(monthKey, month);
    }
    month.days.push({
      date: key,
      day: value.getDate(),
      monthKey,
      monthLabel: month.label,
      summary: summaries.get(key) ?? null,
    });
  }

  return Array.from(months.values());
}

function activityLevel(summary: AiUsageSummary | null, maximum: number): string {
  if (!summary || summary.operations === 0 || maximum <= 0) return '';
  const ratio = summary.operations / maximum;
  if (ratio <= 0.25) return styles.level1;
  if (ratio <= 0.5) return styles.level2;
  if (ratio <= 0.75) return styles.level3;
  return styles.level4;
}

function SummaryCard({ label, summary }: { label: string; summary: AiUsageSummary }) {
  return (
    <Card className={styles.summaryCard}>
      <div className={styles.summaryLabel}>{label}</div>
      <div className={styles.summaryValue}>{number(summary.providerCalls)}</div>
      <div className={styles.summaryMeta}>
        <span>appels Gemini · {number(summary.cacheHits)} évités par cache</span>
        <span>{number(summary.totalTokens)} tokens · cache {summary.cacheHitRate}%</span>
        <span>{money(summary.estimatedCostEur, 'EUR')} · {money(summary.estimatedCostUsd, 'USD')}</span>
      </div>
    </Card>
  );
}

function EventRow({ event }: { event: AiUsageEvent }) {
  const at = new Date(event.atIso);
  return (
    <DataListItem>
      <div className={styles.eventContent}>
        <div className={styles.eventHeader}>
          <div>
            <h3>{purposeLabel(event.purpose)}</h3>
            <div className={styles.eventMeta}>
              <span>{new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'medium' }).format(at)}</span>
              <span>{event.model}</span>
              {event.latencyMs !== null && <span>{number(event.latencyMs)} ms</span>}
              {event.httpStatus !== null && <span>HTTP {event.httpStatus}</span>}
            </div>
          </div>
          <Badge tone={outcomeTone(event.outcome)}>{outcomeLabel(event.outcome)}</Badge>
        </div>
        <div className={styles.eventTokens}>
          <span>Entrée {number(event.inputTokens)}</span>
          <span>Sortie {number(event.outputTokens)}</span>
          {event.thoughtTokens > 0 && <span>Réflexion {number(event.thoughtTokens)}</span>}
          {event.cachedTokens > 0 && <span>Cache Gemini {number(event.cachedTokens)}</span>}
          {event.entityType && event.entityId && <span>{event.entityType} #{event.entityId}</span>}
          {event.errorClass && <span>Erreur : {event.errorClass}</span>}
        </div>
      </div>
      <div className={styles.eventCost}>
        <strong>{event.estimatedCostUsd === null ? 'Non tarifé' : money(event.estimatedCostUsd, 'USD')}</strong>
        <span>estimation locale</span>
      </div>
    </DataListItem>
  );
}

export default function AiUsagePage() {
  const [data, setData] = useState<AiUsagePayload | null>(null);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [billingTier, setBillingTier] = useState<'paid' | 'free'>('paid');
  const [usdToEurRate, setUsdToEurRate] = useState('');
  const [prepaidCreditUsd, setPrepaidCreditUsd] = useState('');

  const apply = useCallback((payload: AiUsagePayload): void => {
    setData(payload);
    setBillingTier(payload.billing.billingTier);
    setUsdToEurRate(payload.billing.usdToEurRate?.toString() ?? '');
    setPrepaidCreditUsd(payload.billing.prepaidCreditUsd?.toString() ?? '');
  }, []);

  const load = useCallback(async (date: string | null): Promise<void> => {
    setError('');
    try {
      const payload = await api<AiUsagePayload>(`/ai/usage${date ? `?date=${encodeURIComponent(date)}` : ''}`);
      apply(payload);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setLoading(false);
    }
  }, [apply]);

  useEffect(() => {
    void load(null);
  }, [load]);

  const months = useMemo(() => calendarMonths(data?.usage.calendar ?? []), [data?.usage.calendar]);
  const maximumDayOperations = useMemo(
    () => Math.max(0, ...(data?.usage.calendar ?? []).map((day) => day.operations)),
    [data?.usage.calendar],
  );

  const chooseDate = (date: string | null): void => {
    setSelectedDate(date);
    setLoading(true);
    void load(date);
  };

  const savePreferences = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    const rate = usdToEurRate.trim();
    const credit = prepaidCreditUsd.trim();
    if (rate !== '' && !Number.isFinite(Number(rate))) {
      setError('Le taux USD vers EUR doit être numérique.');
      setSaving(false);
      return;
    }
    if (credit !== '' && !Number.isFinite(Number(credit))) {
      setError('Le crédit prépayé de référence doit être numérique.');
      setSaving(false);
      return;
    }

    try {
      const payload = await api<AiUsagePayload>('/ai/usage/preferences', {
        method: 'PUT',
        body: JSON.stringify({
          billingTier,
          ...(rate === '' ? { clearUsdToEurRate: true } : { usdToEurRate: Number(rate) }),
          ...(credit === '' ? { clearPrepaidCredit: true } : { prepaidCreditUsd: Number(credit) }),
        }),
      });
      apply(payload);
      setSelectedDate(null);
      setMessage('Préférences d’estimation enregistrées.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  if (loading && data === null && error === '') return <Loading />;
  if (data === null) return <ErrorBox message={error || 'Les statistiques IA sont indisponibles.'} />;

  const { quotaUsage } = data;
  const quotaPercents = {
    rpm: percent(quotaUsage.rpmUsed, quotaUsage.rpmLimit),
    tpm: percent(quotaUsage.tpmUsed, quotaUsage.tpmLimit),
    rpd: percent(quotaUsage.rpdUsed, quotaUsage.rpdLimit),
  };
  const credit = data.usage.credit;
  const eventTitle = selectedDate
    ? `Opérations du ${new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(new Date(`${selectedDate}T12:00:00`))}`
    : 'Opérations récentes';

  return (
    <div className={styles.page}>
      <PageHeader
        title="Utilisation IA"
        description="Suis les vrais appels Gemini, les réponses servies par cache, les quotas locaux, les tokens et le coût estimé sans enregistrer les prompts ni les réponses du modèle."
        actions={<ButtonLink href="/parametres/integrations" variant="secondary">Configurer Gemini</ButtonLink>}
      />

      {error !== '' && <ErrorBox message={error} />}
      {message !== '' && <InlineFeedback tone="success">{message}</InlineFeedback>}

      <div className={styles.heroGrid}>
        <Card>
          <div className={styles.cardTitleRow}>
            <div>
              <h2>État IA</h2>
              <p>État courant du fournisseur utilisé par JobPilot.</p>
            </div>
            <Badge tone={data.enabled && data.apiKeyConfigured ? 'good' : 'warn'}>
              {data.enabled && data.apiKeyConfigured ? 'Active' : data.enabled ? 'Clé requise' : 'Désactivée'}
            </Badge>
          </div>
          <div className={styles.model}>Gemini · {data.model}</div>
          <div className={styles.statusLine}>
            <Badge tone={data.billing.billingTier === 'paid' ? 'blue' : 'neutral'}>
              {data.billing.billingTier === 'paid' ? 'Estimation paid' : 'Estimation free tier'}
            </Badge>
            <span className="small muted">Tarification {data.pricing.version}</span>
          </div>
          {!data.pricing.supported && (
            <InlineFeedback tone="warning">
              Le modèle actuel n’a pas de tarif enregistré dans JobPilot : les tokens restent suivis, mais aucun coût n’est inventé.
            </InlineFeedback>
          )}
        </Card>

        <Card>
          <div className={styles.cardTitleRow}>
            <div>
              <h2>Crédit prépayé</h2>
              <p>Référence locale facultative.</p>
            </div>
          </div>
          <div className={styles.creditValue}>{money(credit.estimatedRemainingUsd, 'USD')}</div>
          {credit.estimatedRemainingEur !== null && <div>{money(credit.estimatedRemainingEur, 'EUR')}</div>}
          <p className={styles.creditHint}>
            {credit.baselineUsd === null
              ? 'Renseigne ton crédit acheté plus bas pour obtenir une estimation du solde restant.'
              : `${money(credit.baselineUsd, 'USD')} saisis comme référence · ${money(credit.trackedCostSinceBaselineUsd, 'USD')} suivis depuis cette référence.`}
          </p>
          <InlineFeedback>
            Ce chiffre est une estimation JobPilot, pas le solde officiel Google AI Studio.
          </InlineFeedback>
        </Card>
      </div>

      <div className={styles.summaryGrid} aria-label="Synthèse de consommation IA">
        <SummaryCard label="Aujourd’hui" summary={data.usage.summaries.today} />
        <SummaryCard label="7 jours" summary={data.usage.summaries.sevenDays} />
        <SummaryCard label="Ce mois" summary={data.usage.summaries.month} />
        <SummaryCard label="Cette année" summary={data.usage.summaries.year} />
      </div>

      <div className={styles.twoColumn}>
        <Card>
          <div className={styles.cardTitleRow}>
            <div>
              <h2>Quotas de sécurité JobPilot</h2>
              <p>Ces compteurs protègent localement l’application ; ils ne sont pas un relevé de facturation Google.</p>
            </div>
          </div>
          <div className={styles.quotaList}>
            <div className={styles.quotaItem}>
              <div className={styles.quotaHeader}><span>Requêtes / minute</span><strong>{quotaUsage.rpmUsed}/{quotaUsage.rpmLimit}</strong></div>
              <ProgressBar value={quotaPercents.rpm} label="Quota de requêtes Gemini par minute" valueText={`${quotaPercents.rpm}%`} tone={quotaPercents.rpm >= 80 ? 'warn' : 'good'} />
            </div>
            <div className={styles.quotaItem}>
              <div className={styles.quotaHeader}><span>Tokens d’entrée / minute</span><strong>{number(quotaUsage.tpmUsed)}/{number(quotaUsage.tpmLimit)}</strong></div>
              <ProgressBar value={quotaPercents.tpm} label="Quota de tokens Gemini par minute" valueText={`${quotaPercents.tpm}%`} tone={quotaPercents.tpm >= 80 ? 'warn' : 'good'} />
            </div>
            <div className={styles.quotaItem}>
              <div className={styles.quotaHeader}><span>Requêtes / jour</span><strong>{quotaUsage.rpdUsed}/{quotaUsage.rpdLimit}</strong></div>
              <ProgressBar value={quotaPercents.rpd} label="Quota de requêtes Gemini par jour" valueText={`${quotaPercents.rpd}%`} tone={quotaPercents.rpd >= 80 ? 'warn' : 'good'} />
            </div>
          </div>
          <p className={styles.footnote}>Réinitialisation quotidienne fournisseur : {quotaUsage.resetTimeZone}. Marge JobPilot : {quotaUsage.safetyPercent}% des plafonds configurés.</p>
        </Card>

        <Card>
          <div className={styles.cardTitleRow}>
            <div>
              <h2>Performance & cache</h2>
              <p>Le cache JobPilot évite complètement l’appel Gemini ; le cache Gemini réduit les tokens d’entrée facturés au tarif cache.</p>
            </div>
          </div>
          <div className={styles.breakdownList}>
            <div className={styles.breakdownItem}><div><strong>{data.usage.summaries.month.cacheHitRate}%</strong><span>Taux de cache JobPilot ce mois</span></div><span>{number(data.usage.summaries.month.cacheHits)} appels évités</span></div>
            <div className={styles.breakdownItem}><div><strong>{number(data.usage.summaries.month.cachedTokens)}</strong><span>Tokens servis depuis le cache Gemini</span></div><span>ce mois</span></div>
            <div className={styles.breakdownItem}><div><strong>{data.usage.summaries.month.averageLatencyMs === null ? '—' : `${number(data.usage.summaries.month.averageLatencyMs)} ms`}</strong><span>Latence moyenne observée</span></div><span>appels suivis</span></div>
            <div className={styles.breakdownItem}><div><strong>{number(data.usage.summaries.month.failedProviderCalls)}</strong><span>Échecs fournisseur</span></div><span>{number(data.usage.summaries.month.quotaBlocked)} blocages quota</span></div>
          </div>
        </Card>
      </div>

      <Card>
        <div className={styles.cardTitleRow}>
          <div>
            <h2>Références de coût</h2>
            <p>Configure uniquement les informations que JobPilot peut utiliser pour ses estimations locales.</p>
          </div>
        </div>
        <div className={styles.preferenceGrid}>
          <FormField label="Niveau de facturation Gemini" hint="Choisis paid si tes appels sont facturés, free si ce projet utilise le niveau sans frais.">
            <select value={billingTier} onChange={(event) => setBillingTier(event.target.value === 'free' ? 'free' : 'paid')}>
              <option value="paid">Paid</option>
              <option value="free">Free tier</option>
            </select>
          </FormField>
          <FormField label="Taux USD → EUR" hint="Facultatif. Sert uniquement à convertir l’estimation en euros.">
            <input inputMode="decimal" value={usdToEurRate} onChange={(event) => setUsdToEurRate(event.target.value)} placeholder="0,85" />
          </FormField>
          <FormField label="Crédit prépayé de référence (USD)" hint="Facultatif. JobPilot soustrait uniquement les coûts qu’il observe après l’enregistrement de cette référence.">
            <input inputMode="decimal" value={prepaidCreditUsd} onChange={(event) => setPrepaidCreditUsd(event.target.value)} placeholder="10" />
          </FormField>
        </div>
        <div className={styles.preferenceActions}>
          <Button loading={saving} onClick={() => void savePreferences()}>Enregistrer les références</Button>
        </div>
        <p className={styles.footnote}>
          Les tarifs intégrés sont versionnés et le modèle non reconnu reste « non tarifé ». Le solde réel et l’historique de transactions restent à vérifier dans Google AI Studio.
        </p>
      </Card>

      <Card>
        <div className={styles.calendarToolbar}>
          <div>
            <h2 className="section-title" style={{ marginBottom: 4 }}>Calendrier d’utilisation</h2>
            <div className="small muted">365 derniers jours · clique sur une journée pour voir les opérations.</div>
          </div>
          <div className={styles.calendarLegend} aria-label="Intensité du nombre d’opérations">
            <span>Moins</span>
            <span className={styles.legendCell} aria-hidden="true" />
            <span className={`${styles.legendCell} ${styles.level1}`} aria-hidden="true" />
            <span className={`${styles.legendCell} ${styles.level2}`} aria-hidden="true" />
            <span className={`${styles.legendCell} ${styles.level3}`} aria-hidden="true" />
            <span className={`${styles.legendCell} ${styles.level4}`} aria-hidden="true" />
            <span>Plus</span>
          </div>
        </div>
        <div className={styles.months}>
          {months.map((month) => (
            <section className={styles.month} key={month.key} aria-label={month.label}>
              <h3>{month.label}</h3>
              <div className={styles.weekdays} aria-hidden="true">{weekdays.map((day, index) => <span key={`${day}-${index}`}>{day}</span>)}</div>
              <div className={styles.days}>
                {Array.from({ length: month.offset }, (_, index) => <span className={styles.dayEmpty} key={`empty-${index}`} aria-hidden="true" />)}
                {month.days.map((day) => {
                  const operations = day.summary?.operations ?? 0;
                  const dayCost = day.summary?.estimatedCostEur ?? null;
                  const label = `${day.date} : ${operations} opération${operations === 1 ? '' : 's'} IA${dayCost !== null ? `, ${money(dayCost, 'EUR')}` : ''}`;
                  return (
                    <button
                      key={day.date}
                      type="button"
                      className={[styles.day, activityLevel(day.summary, maximumDayOperations), selectedDate === day.date ? styles.selected : ''].filter(Boolean).join(' ')}
                      aria-label={label}
                      aria-pressed={selectedDate === day.date}
                      title={label}
                      onClick={() => chooseDate(day.date)}
                    >
                      {day.day}
                    </button>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      </Card>

      <div className={styles.breakdownGrid}>
        <Card>
          <div className={styles.cardTitleRow}><div><h2>Par usage</h2><p>Sur la fenêtre historique affichée.</p></div></div>
          {data.usage.purposes.length === 0 ? <Empty>Aucune utilisation IA enregistrée.</Empty> : (
            <div className={styles.breakdownList}>
              {data.usage.purposes.map((summary) => (
                <div className={styles.breakdownItem} key={summary.purpose}>
                  <div><strong>{purposeLabel(summary.purpose ?? '')}</strong><span>{number(summary.providerCalls)} appels · {number(summary.cacheHits)} cache</span></div>
                  <span>{money(summary.estimatedCostEur, 'EUR')} · {number(summary.totalTokens)} tokens</span>
                </div>
              ))}
            </div>
          )}
        </Card>
        <Card>
          <div className={styles.cardTitleRow}><div><h2>Par modèle</h2><p>Permet de comparer la consommation lorsque plusieurs modèles sont utilisés dans le temps.</p></div></div>
          {data.usage.models.length === 0 ? <Empty>Aucun modèle IA enregistré.</Empty> : (
            <div className={styles.breakdownList}>
              {data.usage.models.map((summary) => (
                <div className={styles.breakdownItem} key={summary.model}>
                  <div><strong>{summary.model}</strong><span>{number(summary.providerCalls)} appels · cache {summary.cacheHitRate}%</span></div>
                  <span>{money(summary.estimatedCostEur, 'EUR')} · {number(summary.totalTokens)} tokens</span>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>

      <Card>
        <div className={styles.cardTitleRow}>
          <div>
            <h2>{eventTitle}</h2>
            <p>Chaque ligne indique si Gemini a réellement été appelé ou si JobPilot a répondu autrement.</p>
          </div>
          {selectedDate && <Button variant="secondary" onClick={() => chooseDate(null)}>Voir les opérations récentes</Button>}
        </div>
        {loading && <div className="small muted" role="status">Actualisation…</div>}
        {data.usage.events.length === 0 ? (
          <Empty>{selectedDate ? 'Aucune opération IA ce jour-là.' : 'Aucune opération IA enregistrée pour le moment.'}</Empty>
        ) : (
          <DataList aria-label={eventTitle}>
            {data.usage.events.map((event) => <EventRow event={event} key={event.id} />)}
          </DataList>
        )}
        <div className={styles.privacyNote}>
          Confidentialité : l’historique conserve les métadonnées techniques, les compteurs de tokens et des identifiants d’entités. Il ne conserve pas le prompt complet, le contenu du CV, la description de l’offre, la clé API ni la réponse du modèle.
        </div>
      </Card>
    </div>
  );
}
