import type { ReactNode } from 'react';

type ButtonGroupProps = {
  children: ReactNode;
  ariaLabel: string;
  className?: string;
};

export function ButtonGroup({ children, ariaLabel, className = '' }: ButtonGroupProps) {
  return (
    <div
      className={['actions', className].filter(Boolean).join(' ')}
      role="group"
      aria-label={ariaLabel}
    >
      {children}
    </div>
  );
}
