import offlineStyles from './offline-state.module.css';

export function PageHeader({ title, description, actions }: { title:string; description?:string; actions?:React.ReactNode }) {
  return <div className="page-header"><div><h1>{title}</h1>{description && <p>{description}</p>}</div>{actions && <div>{actions}</div>}</div>;
}
export function Card({ children, className='' }: { children:React.ReactNode; className?:string }) { return <section className={`card ${className}`}>{children}</section>; }
export function Badge({ children, tone='neutral' }: { children:React.ReactNode; tone?:'neutral'|'good'|'warn'|'bad'|'blue' }) { return <span className={`badge ${tone}`}>{children}</span>; }
export function Empty({ children }: { children:React.ReactNode }) { return <div className="empty">{children}</div>; }
export function Loading() { return <div className="loading">Chargement…</div>; }
export function ErrorBox({ message }: { message:string }) { return <div className="error-box">{message}</div>; }

export function FloatingPanel({
  children,
  id,
  role,
  ariaLabel,
  style,
}: {
  children: React.ReactNode;
  id?: string;
  role?: React.AriaRole;
  ariaLabel?: string;
  style?: React.CSSProperties;
}) {
  return (
    <div
      id={id}
      role={role}
      aria-label={ariaLabel}
      style={{
        padding: 16,
        border: '1px solid var(--line)',
        borderRadius: 12,
        background: 'var(--panel)',
        boxShadow: '0 16px 40px rgba(15, 23, 42, 0.16)',
        ...style,
      }}
    >
      {children}
    </div>
  );
}

export function ProgressBar({
  value,
  label,
  valueText,
}: {
  value: number;
  label: string;
  valueText?: string;
}) {
  const normalizedValue = Math.min(100, Math.max(0, Math.round(value)));

  return (
    <div
      role="progressbar"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={normalizedValue}
      aria-valuetext={valueText}
      style={{
        height: 8,
        borderRadius: 999,
        background: 'var(--surface-muted, #e5e7eb)',
        overflow: 'hidden',
      }}
    >
      <div
        aria-hidden="true"
        style={{
          height: '100%',
          width: `${normalizedValue}%`,
          background: 'currentColor',
          transition: 'width 200ms ease',
        }}
      />
    </div>
  );
}

export function OfflineState({
  title,
  message,
  technicalDetail,
  onRetry,
  retryLabel = 'Réessayer',
}: {
  title: string;
  message: string;
  technicalDetail?: string;
  onRetry: () => void;
  retryLabel?: string;
}) {
  return (
    <section className={offlineStyles.state} role="status" aria-live="polite">
      <div className={offlineStyles.icon} aria-hidden="true">☁</div>
      <div className={offlineStyles.content}>
        <strong className={offlineStyles.title}>{title}</strong>
        <p>{message}</p>
        {technicalDetail && (
          <div className={offlineStyles.detail}>Détail technique : {technicalDetail}</div>
        )}
        <button className="btn secondary small" type="button" onClick={onRetry}>
          {retryLabel}
        </button>
      </div>
    </section>
  );
}
