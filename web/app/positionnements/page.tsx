'use client';

import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { Modal } from '@/components/Modal';
import {
  Badge,
  Button,
  Card,
  DataList,
  DataListItem,
  Empty,
  ErrorBox,
  InlineFeedback,
  Loading,
  PageHeader,
} from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Positioning } from '@/lib/types';

type PositioningForm = {
  finalClient: string;
  agency: string;
  recruiterName: string;
  recruiterEmail: string;
  recruiterPhone: string;
  missionTitle: string;
  description: string;
  callForTenderReference: string;
  advertisedTjmMin: string;
  advertisedTjmMax: string;
  advertisedTjmFixed: string;
  proposedTjm: string;
  acceptedTjm: string;
  startDate: string;
  location: string;
  remotePolicy: string;
  agreementGivenAt: string;
  proofEmailId: string;
  status: string;
};

type DuplicateCheck = {
  duplicate: boolean;
  matches: Array<{
    score: number;
    positioning: {
      id: number;
      finalClient: string;
      agency: string;
      callForTenderReference?: string;
    };
  }>;
};

const initialForm: PositioningForm = {
  finalClient: '',
  agency: '',
  recruiterName: '',
  recruiterEmail: '',
  recruiterPhone: '',
  missionTitle: '',
  description: '',
  callForTenderReference: '',
  advertisedTjmMin: '',
  advertisedTjmMax: '',
  advertisedTjmFixed: '',
  proposedTjm: '',
  acceptedTjm: '',
  startDate: '',
  location: '',
  remotePolicy: 'Hybride',
  agreementGivenAt: '',
  proofEmailId: '',
  status: 'MISSION_DETECTED',
};

function nullableNumber(value: string): number | null {
  return value === '' ? null : Number(value);
}

export default function PositioningsPage() {
  const [items, setItems] = useState<Positioning[] | null>(null);
  const [form, setForm] = useState<PositioningForm>(initialForm);
  const [show, setShow] = useState(false);
  const [error, setError] = useState('');
  const [warning, setWarning] = useState<DuplicateCheck | null>(null);

  const load = useCallback(async (): Promise<void> => {
    try {
      setItems(await api<Positioning[]>('/positionings'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const payload = (): Record<string, unknown> => ({
    ...form,
    advertisedTjmMin: nullableNumber(form.advertisedTjmMin),
    advertisedTjmMax: nullableNumber(form.advertisedTjmMax),
    advertisedTjmFixed: nullableNumber(form.advertisedTjmFixed),
    proposedTjm: nullableNumber(form.proposedTjm),
    acceptedTjm: nullableNumber(form.acceptedTjm),
  });

  const check = async (): Promise<DuplicateCheck | null> => {
    try {
      const result = await api<DuplicateCheck>('/positionings/check-duplicate', {
        method: 'POST',
        body: JSON.stringify(payload()),
      });
      setWarning(result.duplicate ? result : null);
      setError('');
      return result;
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
      return null;
    }
  };

  const save = async (force = false): Promise<void> => {
    setError('');

    try {
      await api('/positionings', {
        method: 'POST',
        body: JSON.stringify({ ...payload(), force }),
      });
      setForm(initialForm);
      setWarning(null);
      setShow(false);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const submit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    void save(false);
  };

  const copyMessage = async (text: string): Promise<void> => {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      setError('Impossible de copier le message dans le presse-papiers.');
    }
  };

  return (
    <>
      <PageHeader
        title="Positionnements"
        description="Suivi des soumissions par client final, agence et commercial."
        actions={
          <Button onClick={() => setShow(true)}>
            Nouveau positionnement
          </Button>
        }
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {items === null ? (
          <Loading />
        ) : items.length === 0 ? (
          <Empty>Aucun positionnement enregistré.</Empty>
        ) : (
          <DataList aria-label="Positionnements enregistrés">
            {items.map((positioning) => (
              <DataListItem key={positioning.id}>
                <div>
                  <div className="actions">
                    <Badge tone={['AGREEMENT_GIVEN', 'SUBMITTED_TO_CLIENT', 'WAITING_CLIENT'].includes(positioning.status) ? 'warn' : 'blue'}>
                      {positioning.status}
                    </Badge>
                    {positioning.callForTenderReference && <Badge>Réf. {positioning.callForTenderReference}</Badge>}
                    {positioning.proposedTjm != null && <Badge tone="good">{positioning.proposedTjm} €</Badge>}
                  </div>
                  <h3>{positioning.missionTitle}</h3>
                  <div className="muted small">
                    Client : {positioning.finalClient} · Agence : {positioning.agency} · Commercial : {positioning.recruiterName}
                  </div>
                  <div className="actions" style={{ marginTop: 9 }}>
                    {positioning.mailtoUrl && <a className="btn secondary small" href={positioning.mailtoUrl}>Préparer l’e-mail d’accord</a>}
                    {positioning.agreementEmailBody && (
                      <Button
                        variant="secondary"
                        size="small"
                        onClick={() => void copyMessage(positioning.agreementEmailBody ?? '')}
                      >
                        Copier le message
                      </Button>
                    )}
                  </div>
                </div>
              </DataListItem>
            ))}
          </DataList>
        )}
      </Card>

      {show && (
        <Modal ariaLabel="Nouveau positionnement" onClose={() => setShow(false)}>
          <PageHeader
            title="Nouveau positionnement"
            actions={
              <Button variant="secondary" onClick={() => setShow(false)}>
                Fermer
              </Button>
            }
          />
          {warning && (
            <InlineFeedback tone="warning" role="alert">
              <strong>Risque de double positionnement.</strong>
              {warning.matches.map((match) => (
                <div key={match.positioning.id} className="small" style={{ marginTop: 8 }}>
                  Similarité {match.score}% — {match.positioning.finalClient} / {match.positioning.agency} / {match.positioning.callForTenderReference || 'sans référence'}
                </div>
              ))}
            </InlineFeedback>
          )}
          <div style={{ height: 12 }} />
          <form className="form-grid" onSubmit={submit}>
            <label>Client final<input required value={form.finalClient} onChange={(e) => setForm({ ...form, finalClient: e.target.value })} /></label>
            <label>Agence / ESN<input required value={form.agency} onChange={(e) => setForm({ ...form, agency: e.target.value })} /></label>
            <label>Commercial<input required value={form.recruiterName} onChange={(e) => setForm({ ...form, recruiterName: e.target.value })} /></label>
            <label>E-mail commercial<input type="email" value={form.recruiterEmail} onChange={(e) => setForm({ ...form, recruiterEmail: e.target.value })} /></label>
            <label>Téléphone commercial<input value={form.recruiterPhone} onChange={(e) => setForm({ ...form, recruiterPhone: e.target.value })} /></label>
            <label>Référence appel d’offres<input value={form.callForTenderReference} onChange={(e) => setForm({ ...form, callForTenderReference: e.target.value })} /></label>
            <label className="full">Intitulé de la mission<input required value={form.missionTitle} onChange={(e) => setForm({ ...form, missionTitle: e.target.value })} /></label>
            <label className="full">Description<textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
            <label>TJM minimum<input type="number" value={form.advertisedTjmMin} onChange={(e) => setForm({ ...form, advertisedTjmMin: e.target.value })} /></label>
            <label>TJM maximum<input type="number" value={form.advertisedTjmMax} onChange={(e) => setForm({ ...form, advertisedTjmMax: e.target.value })} /></label>
            <label>TJM fixe<input type="number" value={form.advertisedTjmFixed} onChange={(e) => setForm({ ...form, advertisedTjmFixed: e.target.value })} /></label>
            <label>TJM proposé<input type="number" placeholder="Calcul automatique si vide" value={form.proposedTjm} onChange={(e) => setForm({ ...form, proposedTjm: e.target.value })} /></label>
            <label>TJM accepté<input type="number" value={form.acceptedTjm} onChange={(e) => setForm({ ...form, acceptedTjm: e.target.value })} /></label>
            <label>Date de démarrage<input type="date" value={form.startDate} onChange={(e) => setForm({ ...form, startDate: e.target.value })} /></label>
            <label>Lieu<input value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} /></label>
            <label>Télétravail<input value={form.remotePolicy} onChange={(e) => setForm({ ...form, remotePolicy: e.target.value })} /></label>
            <label>Date de l’accord<input type="datetime-local" value={form.agreementGivenAt} onChange={(e) => setForm({ ...form, agreementGivenAt: e.target.value })} /></label>
            <label>ID / objet de l’e-mail preuve<input value={form.proofEmailId} onChange={(e) => setForm({ ...form, proofEmailId: e.target.value })} /></label>
            <label>
              Statut
              <select
                aria-label="Statut"
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
              >
                <option value="MISSION_DETECTED">Mission détectée</option>
                <option value="CONTACT_RECRUITER">Contact commercial</option>
                <option value="AGREEMENT_REQUESTED">Accord demandé</option>
                <option value="AGREEMENT_GIVEN">Accord donné</option>
                <option value="SUBMITTED_TO_CLIENT">Soumis au client</option>
                <option value="WAITING_CLIENT">En attente client</option>
                <option value="INTERVIEW_SCHEDULED">Entretien planifié</option>
                <option value="REJECTED">Refusé</option>
                <option value="ACCEPTED">Accepté</option>
                <option value="CANCELLED">Annulé</option>
                <option value="ON_HOLD">En pause</option>
              </select>
            </label>
            <div className="actions full">
              <Button variant="secondary" onClick={() => void check()}>
                Vérifier le doublon
              </Button>
              <Button type="submit">Enregistrer</Button>
              {warning && (
                <Button variant="danger" onClick={() => void save(true)}>
                  Forcer après vérification
                </Button>
              )}
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
