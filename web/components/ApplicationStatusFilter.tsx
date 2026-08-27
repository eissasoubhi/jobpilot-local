'use client';

import { Button } from '@/components/UI';
import {
  applicationStatusLabel,
  applicationStatusOptions,
  filterApplications,
  type ApplicationStatusFilter as ApplicationStatusFilterValue,
} from '@/lib/application-status';
import type { Application } from '@/lib/types';

type ApplicationStatusFilterProps = {
  applications: readonly Application[];
  value: ApplicationStatusFilterValue;
  onChange: (value: ApplicationStatusFilterValue) => void;
};

const QUICK_FILTERS: readonly ApplicationStatusFilterValue[] = [
  'ALL',
  'READY_TO_SUBMIT',
  'SUBMITTED',
];

export function ApplicationStatusFilter({
  applications,
  value,
  onChange,
}: ApplicationStatusFilterProps) {
  const baseOptions = applicationStatusOptions(applications);
  const options = baseOptions.some((option) => option.value === value)
    ? baseOptions
    : [
        ...baseOptions,
        { value, label: applicationStatusLabel(value), count: 0 },
      ];
  const optionByValue = new Map(options.map((option) => [option.value, option]));
  const visibleCount = filterApplications(applications, value).length;

  return (
    <div className="notice" style={{ marginBottom: 14 }}>
      <div className="actions" role="group" aria-label="Filtres rapides des candidatures">
        {QUICK_FILTERS.map((filter) => {
          const option = optionByValue.get(filter);
          if (!option) return null;

          const selected = value === filter;

          return (
            <Button
              variant={selected ? 'primary' : 'secondary'}
              size="small"
              aria-pressed={selected}
              key={filter}
              onClick={() => onChange(filter)}
            >
              {option.label} ({option.count})
            </Button>
          );
        })}
      </div>

      <label htmlFor="application-status-filter" style={{ display: 'block', marginTop: 14 }}>
        Filtrer les candidatures par statut
      </label>
      <select
        id="application-status-filter"
        value={value}
        onChange={(event) => onChange(event.target.value)}
      >
        {options.map((option) => (
          <option value={option.value} key={option.value}>
            {option.label} ({option.count})
          </option>
        ))}
      </select>

      <div className="small muted" role="status" aria-live="polite" style={{ marginTop: 8 }}>
        {visibleCount} candidature(s) affichée(s) sur {applications.length}.
      </div>
    </div>
  );
}
