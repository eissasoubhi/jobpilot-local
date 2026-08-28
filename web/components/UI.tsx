import buttonStyles from './Button.module.css';
import offlineStyles from './offline-state.module.css';
import uiStyles from './UI.module.css';

export function PageHeader({ title, description, actions }: { title:string; description?:string; actions?:React.ReactNode }) {
  return <div className="page-header"><div><h1>{title}</h1>{description && <p>{description}</p>}</div>{actions && <div>{actions}</div>}</div>;
}
export function Card({ children, className='' }: { children:React.ReactNode; className?:string }) { return <section className={`card ${className}`}>{children}</section>; }
export function Badge({ children, tone='neutral' }: { children:React.ReactNode; tone?:'neutral'|'good'|'warn'|'bad'|'blue' }) { return <span className={`badge ${tone}`}>{children}</span>; }
export function Empty({ children }: { children:React.ReactNode }) { return <div className="empty">{children}</div>; }
export function Loading() { return <div className="loading" role="status" aria-live="polite" aria-busy="true">Chargement…</div>; }
export function ErrorBox({ message }: { message:string }) { return <div className="error-box" role="alert">{message}</div>; }

type ButtonVariant = 'primary' | 'secondary' | 'subtle' | 'danger';
type ButtonSize = 'default' | 'small';

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
};

export function Button({
  children,
  className = '',
  disabled,
  loading = false,
  size = 'default',
  type = 'button',
  variant = 'primary',
  ...props
}: ButtonProps) {
  const classes = [
    buttonStyles.button,
    buttonStyles[variant],
    size === 'small' ? buttonStyles.small : '',
    className,
  ].filter(Boolean).join(' ');

  return (
    <button
      {...props}
      type={type}
      className={classes}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
    >
      {loading && <span className={buttonStyles.loadingIndicator} aria-hidden="true" />}
      {children}
    </button>
  );
}

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

type ProgressBarTone = 'neutral' | 'good' | 'warn' | 'bad';
type ProgressBarSize = 'default' | 'compact';

export function ProgressBar({
  value,
  label,
  valueText,
  tone,
  size = 'default',
}: {
  value: number;
  label: string;
  valueText?: string;
  tone?: ProgressBarTone;
  size?: ProgressBarSize;
}) {
  const normalizedValue = Math.min(100, Math.max(0, Math.round(value)));
  const indicatorColor = tone === 'good'
    ? 'var(--good)'
    : tone === 'warn'
      ? 'var(--warn)'
      : tone === 'bad'
        ? 'var(--bad)'
        : tone === 'neutral'
          ? 'var(--primary)'
          : 'currentColor';
  const trackColor = tone === undefined ? 'var(--surface-muted, #e5e7eb)' : 'var(--line)';

  return (
    <div
      role="progressbar"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={normalizedValue}
      aria-valuetext={valueText}
      style={{
        height: size === 'compact' ? 4 : 8,
        borderRadius: 999,
        background: trackColor,
        overflow: 'hidden',
      }}
    >
      <div
        className={uiStyles.progressIndicator}
        aria-hidden="true"
        style={{
          height: '100%',
          width: `${normalizedValue}%`,
          background: indicatorColor,
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
        <Button variant="secondary" size="small" onClick={onRetry}>
          {retryLabel}
        </Button>
      </div>
    </section>
  );
}
