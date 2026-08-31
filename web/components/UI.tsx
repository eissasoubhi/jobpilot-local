import { cloneElement, isValidElement, useId } from 'react';
import Link from 'next/link';

import buttonStyles from './Button.module.css';
import offlineStyles from './offline-state.module.css';
import uiStyles from './UI.module.css';

export function PageHeader({ title, description, actions }: { title:string; description?:string; actions?:React.ReactNode }) {
  return <div className="page-header"><div><h1>{title}</h1>{description && <p>{description}</p>}</div>{actions && <div>{actions}</div>}</div>;
}
export function Card({ children, className='' }: { children:React.ReactNode; className?:string }) { return <section className={`card ${className}`}>{children}</section>; }
export function Badge({ children, tone='neutral' }: { children:React.ReactNode; tone?:'neutral'|'good'|'warn'|'bad'|'blue' }) { return <span className={`badge ${tone}`}>{children}</span>; }
export function Empty({ children }: { children:React.ReactNode }) { return <div className="empty" role="status" aria-live="polite">{children}</div>; }
export function Loading() { return <div className="loading" role="status" aria-live="polite" aria-busy="true">Chargement…</div>; }
export function ErrorBox({ message }: { message:string }) { return <div className="error-box" role="alert">{message}</div>; }

export function InlineFeedback({
  children,
  className = '',
  role = 'status',
  tone = 'info',
}: {
  children: React.ReactNode;
  className?: string;
  role?: 'status' | 'alert';
  tone?: 'info' | 'success' | 'warning';
}) {
  return (
    <div
      className={[
        uiStyles.inlineFeedback,
        uiStyles[`inlineFeedback${tone[0].toUpperCase()}${tone.slice(1)}`],
        className,
      ].filter(Boolean).join(' ')}
      role={role}
      aria-live={role === 'alert' ? 'assertive' : 'polite'}
    >
      {children}
    </div>
  );
}

type DataToolbarProps = React.HTMLAttributes<HTMLDivElement> & {
  actions?: React.ReactNode;
};

export function DataToolbar({
  children,
  actions,
  className = '',
  ...props
}: DataToolbarProps) {
  return (
    <div {...props} className={[uiStyles.dataToolbar, className].filter(Boolean).join(' ')}>
      <div className={uiStyles.dataToolbarContent}>{children}</div>
      {actions && <div className={uiStyles.dataToolbarActions}>{actions}</div>}
    </div>
  );
}

export function DataList({
  children,
  className = '',
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      {...props}
      className={[uiStyles.dataList, className].filter(Boolean).join(' ')}
      role="list"
    >
      {children}
    </div>
  );
}

export function DataListItem({
  children,
  className = '',
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      {...props}
      className={[uiStyles.dataListItem, className].filter(Boolean).join(' ')}
      role="listitem"
    >
      {children}
    </div>
  );
}

type ButtonVariant = 'primary' | 'secondary' | 'subtle' | 'danger';
type ButtonSize = 'default' | 'small';

function buttonClasses(variant: ButtonVariant, size: ButtonSize, className: string): string {
  return [
    buttonStyles.button,
    buttonStyles[variant],
    size === 'small' ? buttonStyles.small : '',
    className,
  ].filter(Boolean).join(' ');
}

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
  return (
    <button
      {...props}
      type={type}
      className={buttonClasses(variant, size, className)}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
    >
      {loading && <span className={buttonStyles.loadingIndicator} aria-hidden="true" />}
      {children}
    </button>
  );
}

type ButtonLinkProps = React.ComponentProps<typeof Link> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
};

export function ButtonLink({
  children,
  className = '',
  size = 'default',
  variant = 'primary',
  ...props
}: ButtonLinkProps) {
  return (
    <Link {...props} className={buttonClasses(variant, size, className)}>
      {children}
    </Link>
  );
}

type FormControlProps = {
  id?: string;
  'aria-describedby'?: string;
  'aria-invalid'?: React.AriaAttributes['aria-invalid'];
};

export function FormField({
  children,
  error,
  label,
  hint,
  success,
}: {
  children: React.ReactNode;
  error?: React.ReactNode;
  label: React.ReactNode;
  hint?: React.ReactNode;
  success?: React.ReactNode;
}) {
  const fieldId = useId();
  const controlId = isValidElement<FormControlProps>(children) && children.props.id
    ? children.props.id
    : `${fieldId}-control`;
  const hintId = hint ? `${fieldId}-hint` : undefined;
  const errorId = error ? `${fieldId}-error` : undefined;
  const successId = success && !error ? `${fieldId}-success` : undefined;

  let control = children;
  if (isValidElement<FormControlProps>(children)) {
    const describedBy = [children.props['aria-describedby'], hintId, errorId, successId]
      .filter(Boolean)
      .join(' ') || undefined;

    control = cloneElement(children, {
      id: controlId,
      'aria-describedby': describedBy,
      'aria-invalid': error ? true : children.props['aria-invalid'],
    });
  }

  return (
    <div className={uiStyles.formField}>
      <label htmlFor={controlId} className={uiStyles.formFieldLabel}>{label}</label>
      {control}
      {hint && <span id={hintId} className={uiStyles.formFieldHint}>{hint}</span>}
      {error && <span id={errorId} className={uiStyles.formFieldError} role="alert">{error}</span>}
      {successId && (
        <span id={successId} className={uiStyles.formFieldSuccess} role="status" aria-live="polite">
          {success}
        </span>
      )}
    </div>
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
      className={uiStyles.floatingPanel}
      style={style}
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
