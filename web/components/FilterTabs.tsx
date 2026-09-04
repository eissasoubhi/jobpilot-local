import type { KeyboardEvent, ReactNode } from 'react';

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
  const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number): void => {
    if (options.length === 0) return;

    let nextIndex: number | null = null;

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') nextIndex = (index + 1) % options.length;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') nextIndex = (index - 1 + options.length) % options.length;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = options.length - 1;

    if (nextIndex === null) return;

    event.preventDefault();

    if (nextIndex !== index) {
      onChange(options[nextIndex].value);
    }

    event.currentTarget.parentElement
      ?.querySelectorAll<HTMLButtonElement>('[role="radio"]')[nextIndex]
      ?.focus();
  };

  return (
    <div className="tabs" role="radiogroup" aria-label={ariaLabel}>
      {options.map((option, index) => {
        const selected = option.value === value;

        return (
          <button
            key={option.value}
            className={selected ? 'active' : ''}
            type="button"
            role="radio"
            aria-checked={selected}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(option.value)}
            onKeyDown={(event) => handleKeyDown(event, index)}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}
