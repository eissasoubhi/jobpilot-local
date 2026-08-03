'use client';

import { useEffect } from 'react';

import { ErrorBox, PageHeader } from '@/components/UI';

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <>
      <PageHeader title="Une erreur est survenue" />
      <ErrorBox message={error.message || 'Erreur inattendue.'} />
      <button className="btn" type="button" onClick={reset}>
        Réessayer
      </button>
    </>
  );
}
