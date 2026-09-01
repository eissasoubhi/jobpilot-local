import uiStyles from './UI.module.css';

export function SkeletonGroup({
  children,
  label = 'Chargement…',
  className = '',
}: {
  children: React.ReactNode;
  label?: string;
  className?: string;
}) {
  return (
    <div
      className={[uiStyles.skeletonGroup, className].filter(Boolean).join(' ')}
      role="status"
      aria-label={label}
      aria-live="polite"
      aria-busy="true"
    >
      {children}
    </div>
  );
}

export function Skeleton({
  height = 16,
  width = '100%',
  className = '',
}: {
  height?: number | string;
  width?: number | string;
  className?: string;
}) {
  return (
    <span
      aria-hidden="true"
      className={[uiStyles.skeleton, className].filter(Boolean).join(' ')}
      style={{ height, width }}
    />
  );
}
