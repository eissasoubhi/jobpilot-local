export function PageHeader({ title, description, actions }: { title:string; description?:string; actions?:React.ReactNode }) {
  return <div className="page-header"><div><h1>{title}</h1>{description && <p>{description}</p>}</div>{actions && <div>{actions}</div>}</div>;
}
export function Card({ children, className='' }: { children:React.ReactNode; className?:string }) { return <section className={`card ${className}`}>{children}</section>; }
export function Badge({ children, tone='neutral' }: { children:React.ReactNode; tone?:'neutral'|'good'|'warn'|'bad'|'blue' }) { return <span className={`badge ${tone}`}>{children}</span>; }
export function Empty({ children }: { children:React.ReactNode }) { return <div className="empty">{children}</div>; }
export function Loading() { return <div className="loading">Chargement…</div>; }
export function ErrorBox({ message }: { message:string }) { return <div className="error-box">{message}</div>; }

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
    <section className="offline-state" role="status" aria-live="polite">
      <div className="offline-state-icon" aria-hidden="true">☁</div>
      <div className="offline-state-content">
        <strong className="offline-state-title">{title}</strong>
        <p>{message}</p>
        {technicalDetail && (
          <div className="offline-state-detail">Détail technique : {technicalDetail}</div>
        )}
        <button className="btn secondary small" type="button" onClick={onRetry}>
          {retryLabel}
        </button>
      </div>
    </section>
  );
}
