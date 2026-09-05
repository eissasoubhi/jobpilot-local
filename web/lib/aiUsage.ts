export type AiQuotaUsage = {
  rpmUsed: number;
  tpmUsed: number;
  rpdUsed: number;
  rpmLimit: number;
  tpmLimit: number;
  rpdLimit: number;
  providerRpm: number;
  providerTpm: number;
  providerRpd: number;
  safetyPercent: number;
  resetsAt: string;
  resetTimeZone: string;
};

export type AiUsageSummary = {
  operations: number;
  providerCalls: number;
  successfulProviderCalls: number;
  failedProviderCalls: number;
  cacheHits: number;
  quotaBlocked: number;
  inputTokens: number;
  outputTokens: number;
  thoughtTokens: number;
  cachedTokens: number;
  toolUseTokens: number;
  totalTokens: number;
  estimatedCostUsd: number;
  estimatedCostEur: number | null;
  pricedCalls: number;
  unpricedCalls: number;
  averageLatencyMs: number | null;
  cacheHitRate: number;
  date?: string;
  purpose?: string;
  model?: string;
};

export type AiUsageEvent = {
  id: string;
  at: number;
  atIso: string;
  provider: string;
  model: string;
  purpose: string;
  outcome: string;
  providerCall: boolean;
  cacheHit: boolean;
  quotaBlocked: boolean;
  inputTokens: number;
  outputTokens: number;
  thoughtTokens: number;
  cachedTokens: number;
  toolUseTokens: number;
  totalTokens: number;
  latencyMs: number | null;
  entityType: string | null;
  entityId: string | null;
  httpStatus: number | null;
  errorClass: string | null;
  estimatedCostUsd: number | null;
  pricingVersion: string;
  pricingSupported: boolean;
};

export type AiUsagePayload = {
  provider: string;
  enabled: boolean;
  model: string;
  apiKeyConfigured: boolean;
  quota: {
    rpm: number;
    tpm: number;
    rpd: number;
    safetyPercent: number;
  };
  quotaUsage: AiQuotaUsage;
  billing: {
    billingTier: 'paid' | 'free';
    usdToEurRate: number | null;
    prepaidCreditUsd: number | null;
    prepaidCreditSetAt: number | null;
    prepaidCreditSetAtIso: string | null;
  };
  pricing: {
    supported: boolean;
    source: string;
    version: string;
    inputPerMillionUsd: number | null;
    outputPerMillionUsd: number | null;
    cachedInputPerMillionUsd: number | null;
  };
  usage: {
    timezone: string;
    selectedDate: string | null;
    summaries: {
      today: AiUsageSummary;
      sevenDays: AiUsageSummary;
      month: AiUsageSummary;
      year: AiUsageSummary;
    };
    calendar: AiUsageSummary[];
    purposes: AiUsageSummary[];
    models: AiUsageSummary[];
    events: AiUsageEvent[];
    credit: {
      baselineUsd: number | null;
      baselineAt: string | null;
      trackedCostSinceBaselineUsd: number | null;
      estimatedRemainingUsd: number | null;
      estimatedRemainingEur: number | null;
      label: 'local_estimate';
    };
  };
};
