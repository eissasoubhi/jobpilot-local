'use client';

import { FilterTabs } from '@/components/FilterTabs';
import { FormField, InlineFeedback } from '@/components/UI';
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
  const quickOptions = QUICK_FILTERS.flatMap((filter) => {
    const option = optionByValue.get(filter);

    return option
      ? [{ value: filter, label: `${option.label} (${option.count})` }]
      : [];
  });

  return (
    <div className="notice" style={{ marginBottom: 14 }}>
      <FilterTabs
        ariaLabel="Filtres rapides des candidatures"
        options={quickOptions}
        value={value}
        onChange={onChange}
      />

      <div style={{ marginTop: 14 }}>
        <FormField label="Filtrer les candidatures par statut">
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
        </FormField>
      </div>

      <div style={{ marginTop: 8 }}>
        <InlineFeedback>
          {visibleCount} candidature(s) affichée(s) sur {applications.length}.
        </InlineFeedback>
      </div>
    </div>
  );
}
