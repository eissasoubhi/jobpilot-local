import type { ReactNode } from 'react';

type FilterTabOption<T extends string> = {
  value: T;
  label: ReactNode;
};

type FilterTabsProps<T extends string> = {
  ariaLabel: string;
  options: readonly FilterTabOption<T>[];
  value: T;
  onChange: (value: T) => void;
};

export function FilterTabs<T extends string>({ ariaLabel, options, value, onChange }: FilterTabsProps<T>) {
  return (
    <div className="tabs" role="group" aria-label={ariaLabel}>
      {options.map((option) => {
        const selected = option.value === value;

        return (
          <button
            key={option.value}
            className={selected ? 'active' : ''}
            type="button"
            aria-pressed={selected}
            onClick={() => onChange(option.value)}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
