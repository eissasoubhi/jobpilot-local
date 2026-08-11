'use client';

import { useEffect, useState } from 'react';

import { Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Profile } from '@/lib/types';

const splitLines = (value: string): string[] => value.split('\n').map((item) => item.trim()).filter(Boolean);

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
    setMessage('');
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

  const technologyExperience = Object.entries(profile.technologyExperience)
    .map(([technology, years]) => `${technology}: ${years}`)
    .join('\n');

  return (
    <>
      <PageHeader
        title="Profil candidat"
        description="Source unique utilisée par JobPilot pour préparer et, bientôt, préremplir les formulaires de candidature."
        actions={<button className="btn" type="button" onClick={() => void save()}>Enregistrer</button>}
      />
      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />

      <Card>
        <h2>Identité et coordonnées</h2>
        <div className="form-grid">
          <label>Prénom<input value={profile.firstName} onChange={(e) => set('firstName', e.target.value)} autoComplete="given-name" /></label>
          <label>Nom<input value={profile.lastName} onChange={(e) => set('lastName', e.target.value)} autoComplete="family-name" /></label>
          <label>Nom complet<input value={profile.fullName} onChange={(e) => set('fullName', e.target.value)} autoComplete="name" /></label>
          <label>E-mail<input type="email" value={profile.email} onChange={(e) => set('email', e.target.value)} autoComplete="email" /></label>
          <label>Téléphone<input value={profile.phone} onChange={(e) => set('phone', e.target.value)} autoComplete="tel" /></label>
          <label>Adresse<input value={profile.addressLine1} onChange={(e) => set('addressLine1', e.target.value)} autoComplete="address-line1" /></label>
          <label>Complément d’adresse<input value={profile.addressLine2 ?? ''} onChange={(e) => set('addressLine2', e.target.value)} autoComplete="address-line2" /></label>
          <label>Ville<input value={profile.city} onChange={(e) => set('city', e.target.value)} autoComplete="address-level2" /></label>
          <label>Code postal<input value={profile.postalCode} onChange={(e) => set('postalCode', e.target.value)} autoComplete="postal-code" /></label>
          <label>Région<input value={profile.region} onChange={(e) => set('region', e.target.value)} autoComplete="address-level1" /></label>
          <label>Pays<input value={profile.country} onChange={(e) => set('country', e.target.value)} autoComplete="country-name" /></label>
          <label>Code pays<input maxLength={2} value={profile.countryCode} onChange={(e) => set('countryCode', e.target.value.toUpperCase())} autoComplete="country" /></label>
        </div>
      </Card>

      <div style={{ height: 14 }} />
      <Card>
        <h2>Profil professionnel</h2>
        <div className="form-grid">
          <label>Poste actuel<input value={profile.currentJobTitle} onChange={(e) => set('currentJobTitle', e.target.value)} /></label>
          <label>Années d’expérience<input type="number" min={0} value={profile.yearsOfExperience} onChange={(e) => set('yearsOfExperience', Number(e.target.value))} /></label>
          <label>LinkedIn<input value={profile.linkedinUrl ?? ''} onChange={(e) => set('linkedinUrl', e.target.value)} /></label>
          <label>GitHub<input value={profile.githubUrl ?? ''} onChange={(e) => set('githubUrl', e.target.value)} /></label>
          <label>Portfolio<input value={profile.portfolioUrl ?? ''} onChange={(e) => set('portfolioUrl', e.target.value)} /></label>
          <label className="full">Autres URLs professionnelles (une par ligne)<textarea value={profile.professionalUrls.join('\n')} onChange={(e) => set('professionalUrls', splitLines(e.target.value))} /></label>
          <label className="full">Expérience par technologie (Technologie: années)<textarea
            value={technologyExperience}
            onChange={(event) => {
              const entries = splitLines(event.target.value).map((line) => {
                const [technology, years] = line.split(':');
                return [technology?.trim() ?? '', Math.max(0, Number(years?.trim() ?? 0))] as const;
              }).filter(([technology]) => technology !== '');
              set('technologyExperience', Object.fromEntries(entries));
            }}
          /></label>
          <label className="full">Langues (une ligne par langue : niveau)<textarea
            value={profile.languages.map((language) => `${language.language}: ${language.level}`).join('\n')}
            onChange={(event) => set('languages', splitLines(event.target.value).map((line) => {
              const [language, ...rest] = line.split(':');
              return { language: language.trim(), level: rest.join(':').trim() };
            }))}
          /></label>
        </div>
      </Card>

      <div style={{ height: 14 }} />
      <Card>
        <h2>Préférences de candidature</h2>
        <div className="form-grid">
          <label>Mobilité<input value={profile.mobility} onChange={(e) => set('mobility', e.target.value)} /></label>
          <label>Localisations préférées<input value={profile.preferredLocations.join(', ')} onChange={(e) => set('preferredLocations', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} /></label>
          <label>Autorisation de travail<input value={profile.workAuthorisation} onChange={(e) => set('workAuthorisation', e.target.value)} /></label>
          <label>Disponibilité<input value={profile.availability} onChange={(e) => set('availability', e.target.value)} /></label>
          <label>Préavis<input value={profile.noticePeriod} onChange={(e) => set('noticePeriod', e.target.value)} /></label>
          <label>Préférence télétravail / site<input value={profile.workModePreference} onChange={(e) => set('workModePreference', e.target.value)} /></label>
          <label>Contrats acceptés<input value={profile.acceptedContracts.join(', ')} onChange={(e) => set('acceptedContracts', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} /></label>
          <label>Salaire souhaité (€ brut/an)<input type="number" min={0} value={profile.desiredSalary ?? ''} onChange={(e) => set('desiredSalary', e.target.value === '' ? null : Number(e.target.value))} /></label>
          <label>TJM souhaité (€)<input type="number" min={0} value={profile.desiredTjm ?? ''} onChange={(e) => set('desiredTjm', e.target.value === '' ? null : Number(e.target.value))} /></label>
        </div>
      </Card>
    </>
  );
}
