'use client';

import { useEffect } from 'react';

import { Button, ErrorBox, PageHeader } from '@/components/UI';

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
      <Button type="button" onClick={reset}>
        Réessayer
      </Button>
    </>
  );
}
