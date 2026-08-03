'use client';

import { useEffect, useState } from 'react';

import { Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Profile } from '@/lib/types';

export default function ProfilePage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<Profile>('/profile')
      .then((result) => {
        if (active) setProfile(result);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  if (profile === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  const set = <K extends keyof Profile>(key: K, value: Profile[K]): void => {
    setProfile({ ...profile, [key]: value });
  };

  const save = async (): Promise<void> => {
    setError('');
    try {
      setProfile(await api<Profile>('/profile', {
        method: 'PUT',
        body: JSON.stringify(profile),
      }));
      setMessage('Profil enregistré.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  return (
    <>
      <PageHeader
        title="Profil candidat"
        description="Ces données servent uniquement à remplir les formulaires et générer les messages."
        actions={
          <button className="btn" type="button" onClick={() => void save()}>
            Enregistrer
          </button>
        }
      />
      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />
      <Card>
        <div className="form-grid">
          <label>Nom complet<input value={profile.fullName} onChange={(e) => set('fullName', e.target.value)} /></label>
          <label>E-mail<input type="email" value={profile.email} onChange={(e) => set('email', e.target.value)} /></label>
          <label>Téléphone<input value={profile.phone} onChange={(e) => set('phone', e.target.value)} /></label>
          <label>Ville<input value={profile.city} onChange={(e) => set('city', e.target.value)} /></label>
          <label>Code postal<input value={profile.postalCode} onChange={(e) => set('postalCode', e.target.value)} /></label>
          <label>Mobilité<input value={profile.mobility} onChange={(e) => set('mobility', e.target.value)} /></label>
          <label>Autorisation de travail<input value={profile.workAuthorisation} onChange={(e) => set('workAuthorisation', e.target.value)} /></label>
          <label>Disponibilité<input value={profile.availability} onChange={(e) => set('availability', e.target.value)} /></label>
          <label>Préavis<input value={profile.noticePeriod} onChange={(e) => set('noticePeriod', e.target.value)} /></label>
          <label>Années d’expérience<input type="number" value={profile.yearsOfExperience} onChange={(e) => set('yearsOfExperience', Number(e.target.value))} /></label>
          <label>Préférence télétravail / site<input value={profile.workModePreference} onChange={(e) => set('workModePreference', e.target.value)} /></label>
          <label>Contrats acceptés<input value={profile.acceptedContracts.join(', ')} onChange={(e) => set('acceptedContracts', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} /></label>
          <label>LinkedIn<input value={profile.linkedinUrl ?? ''} onChange={(e) => set('linkedinUrl', e.target.value)} /></label>
          <label>Portfolio / GitHub<input value={profile.portfolioUrl ?? ''} onChange={(e) => set('portfolioUrl', e.target.value)} /></label>
          <label className="full">
            Langues (une ligne par langue : niveau)
            <textarea
              value={profile.languages.map((language) => `${language.language}: ${language.level}`).join('\n')}
              onChange={(event) => set(
                'languages',
                event.target.value
                  .split('\n')
                  .filter(Boolean)
                  .map((line) => {
                    const [language, ...rest] = line.split(':');
                    return { language: language.trim(), level: rest.join(':').trim() };
                  }),
              )}
            />
          </label>
        </div>
      </Card>
    </>
  );
}
