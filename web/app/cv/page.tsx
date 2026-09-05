'use client';

import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  DataList,
  DataListItem,
  Empty,
  ErrorBox,
  FormField,
  InlineFeedback,
  PageHeader,
} from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Cv } from '@/lib/types';

import styles from './cv.module.css';

function CvDocumentsSkeleton() {
  return (
    <SkeletonGroup label="Chargement des CV">
      <DataList aria-hidden="true">
        {[0, 1, 2].map((index) => (
          <DataListItem key={index} className={styles.documentItem}>
            <div className={styles.skeletonContent}>
              <Skeleton width="58%" height={22} />
              <div className={styles.skeletonBadges}>
                <Skeleton width="74%" height={16} />
              </div>
              <div className={styles.skeletonBadges}>
                <Skeleton width={82} height={24} />
                <Skeleton width={92} height={24} />
              </div>
            </div>
            <div className={styles.skeletonActions}>
              <Skeleton width={96} height={34} />
              <Skeleton width={84} height={34} />
            </div>
          </DataListItem>
        ))}
      </DataList>
    </SkeletonGroup>
  );
}

export default function CvPage() {
  const [items, setItems] = useState<Cv[] | null>(null);
  const [loadError, setLoadError] = useState('');
  const [uploadError, setUploadError] = useState('');
  const [documentsError, setDocumentsError] = useState('');
  const [uploading, setUploading] = useState(false);
  const [removingId, setRemovingId] = useState<number | null>(null);
  const [pendingRemovalId, setPendingRemovalId] = useState<number | null>(null);
  const [uploadMessage, setUploadMessage] = useState('');
  const [documentsMessage, setDocumentsMessage] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      setItems(await api<Cv[]>('/cvs'));
      setLoadError('');
    } catch (caughtError: unknown) {
      setLoadError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const upload = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    setUploadError('');
    setUploadMessage('');
    setUploading(true);
    const form = event.currentTarget;

    try {
      await api<Cv>('/cvs', { method: 'POST', body: new FormData(form) });
      form.reset();
      await load();
      setUploadMessage('CV téléversé.');
    } catch (caughtError: unknown) {
      setUploadError(getErrorMessage(caughtError));
    } finally {
      setUploading(false);
    }
  };

  const remove = async (id: number): Promise<void> => {
    setDocumentsError('');
    setDocumentsMessage('');
    setRemovingId(id);

    try {
      await api(`/cvs/${id}`, { method: 'DELETE' });
      await load();
      setDocumentsMessage('CV supprimé.');
      setPendingRemovalId(null);
    } catch (caughtError: unknown) {
      setDocumentsError(getErrorMessage(caughtError));
    } finally {
      setRemovingId(null);
    }
  };

  return (
    <>
      <PageHeader
        title="Mes CV"
        description="L’application choisit le document adapté, sans modifier son contenu."
      />
      <div className={styles.layout}>
        <Card>
          <h2 className={styles.sectionTitle}>Ajouter un CV</h2>
          <form className="stack" onSubmit={(event) => void upload(event)}>
            <FormField label="Nom du CV">
              <input name="name" required placeholder="CV Full-Stack Symfony React" />
            </FormField>
            <FormField label="Langue">
              <select name="language">
                <option value="fr">Français</option>
                <option value="en">Anglais</option>
              </select>
            </FormField>
            <FormField label="Catégorie">
              <input name="category" placeholder="Full-Stack, Backend, Frontend…" />
            </FormField>
            <FormField label="Tags">
              <input name="tags" placeholder="Symfony, React, PHP" />
            </FormField>
            <FormField label="Fichier PDF ou Word">
              <input name="file" type="file" accept=".pdf,.doc,.docx" required />
            </FormField>
            <label className="checkbox-label">
              <input name="defaultForLanguage" type="checkbox" value="true" />
              CV par défaut pour cette langue
            </label>
            {uploadError !== '' && <ErrorBox message={uploadError} />}
            {uploadMessage !== '' && (
              <InlineFeedback tone="success">{uploadMessage}</InlineFeedback>
            )}
            <Button type="submit" loading={uploading} className={styles.uploadButton}>
              Téléverser
            </Button>
          </form>
        </Card>

        <Card>
          <h2 className={styles.sectionTitle}>Documents disponibles</h2>
          {loadError !== '' && <ErrorBox message={loadError} />}
          {documentsError !== '' && <ErrorBox message={documentsError} />}
          {documentsMessage !== '' && (
            <InlineFeedback tone="success">{documentsMessage}</InlineFeedback>
          )}
          {items === null ? (
            loadError === '' ? <CvDocumentsSkeleton /> : null
          ) : items.length === 0 ? (
            <Empty>Aucun CV téléversé.</Empty>
          ) : (
            <DataList aria-label="CV disponibles">
              {items.map((cv) => (
                <DataListItem key={cv.id} className={styles.documentItem}>
                  <div className={styles.documentContent}>
                    <h3 className={styles.documentName}>{cv.name}</h3>
                    <div className={`muted small ${styles.documentMeta}`}>
                      {cv.originalName} · {(cv.size / 1024).toFixed(0)} Ko
                    </div>
                    <div className={styles.badges}>
                      <Badge tone="blue">{cv.language === 'fr' ? 'Français' : 'Anglais'}</Badge>
                      {cv.defaultForLanguage && <Badge tone="good">Par défaut</Badge>}
                      {cv.tags.map((tag) => <Badge key={tag}>{tag}</Badge>)}
                    </div>
                  </div>
                  <div className={styles.rowActions}>
                    <ButtonLink variant="secondary" size="small" href={cv.downloadUrl}>
                      Télécharger
                    </ButtonLink>
                    <Button
                      variant="danger"
                      size="small"
                      loading={removingId === cv.id}
                      disabled={removingId !== null && removingId !== cv.id}
                      onClick={() => setPendingRemovalId(cv.id)}
                    >
                      Supprimer
                    </Button>
                  </div>
                </DataListItem>
              ))}
            </DataList>
          )}
        </Card>
      </div>
      <ConfirmDialog
        open={pendingRemovalId !== null}
        title="Supprimer ce CV ?"
        description="Ce document sera supprimé de JobPilot. Cette action est irréversible."
        confirmLabel="Supprimer"
        loading={removingId !== null}
        onCancel={() => setPendingRemovalId(null)}
        onConfirm={() => {
          if (pendingRemovalId !== null) void remove(pendingRemovalId);
        }}
      />
    </>
  );
}
