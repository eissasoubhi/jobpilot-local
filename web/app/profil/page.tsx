'use client';

import { useEffect, useState } from 'react';

import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Button, Card, ErrorBox, FormField, InlineFeedback, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Profile } from '@/lib/types';
import styles from './profile.module.css';

const splitLines = (value: string): string[] => value.split('\n').map((item) => item.trim()).filter(Boolean);

function ProfileSkeleton() {
  const sections = [12, 7, 9];

  return (
    <>
      <PageHeader
        title="Profil candidat"
        description="Source unique utilisée par JobPilot pour préparer et, bientôt, préremplir les formulaires de candidature."
      />
      <SkeletonGroup label="Chargement du profil candidat">
        <div className={styles.profileStack} aria-hidden="true">
          {sections.map((fieldCount, sectionIndex) => (
            <Card key={sectionIndex} className={styles.skeletonSection}>
              <Skeleton width={sectionIndex === 0 ? 190 : 220} height={24} />
              <div className={styles.formGrid}>
                {Array.from({ length: fieldCount }, (_, fieldIndex) => (
                  <div key={fieldIndex} className={styles.skeletonField}>
                    <Skeleton width="42%" height={14} />
                    <Skeleton height={40} />
                  </div>
                ))}
              </div>
            </Card>
          ))}
        </div>
      </SkeletonGroup>
    </>
  );
}

export default function ProfilePage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

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
    return error !== '' ? <ErrorBox message={error} /> : <ProfileSkeleton />;
  }

  const set = <K extends keyof Profile>(key: K, value: Profile[K]): void => {
    setProfile({ ...profile, [key]: value });
    setMessage('');
  };

  const save = async (): Promise<void> => {
    setError('');
    setMessage('');
    setSaving(true);
    try {
      setProfile(await api<Profile>('/profile', {
        method: 'PUT',
        body: JSON.stringify(profile),
      }));
      setMessage('Profil enregistré.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
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
        actions={(
          <Button className={styles.saveAction} type="button" loading={saving} onClick={() => void save()}>
            Enregistrer
          </Button>
        )}
      />

      <div className={styles.profileStack}>
        {message !== '' && <InlineFeedback tone="success">{message}</InlineFeedback>}
        {error !== '' && <ErrorBox message={error} />}

        <Card>
          <h2 className={styles.sectionTitle}>Identité et coordonnées</h2>
          <div className={styles.formGrid}>
            <FormField label="Prénom">
              <input value={profile.firstName} onChange={(e) => set('firstName', e.target.value)} autoComplete="given-name" />
            </FormField>
            <FormField label="Nom">
              <input value={profile.lastName} onChange={(e) => set('lastName', e.target.value)} autoComplete="family-name" />
            </FormField>
            <FormField label="Nom complet">
              <input value={profile.fullName} onChange={(e) => set('fullName', e.target.value)} autoComplete="name" />
            </FormField>
            <FormField label="E-mail">
              <input type="email" value={profile.email} onChange={(e) => set('email', e.target.value)} autoComplete="email" />
            </FormField>
            <FormField label="Téléphone">
              <input value={profile.phone} onChange={(e) => set('phone', e.target.value)} autoComplete="tel" />
            </FormField>
            <FormField label="Adresse">
              <input value={profile.addressLine1} onChange={(e) => set('addressLine1', e.target.value)} autoComplete="address-line1" />
            </FormField>
            <FormField label="Complément d’adresse">
              <input value={profile.addressLine2 ?? ''} onChange={(e) => set('addressLine2', e.target.value)} autoComplete="address-line2" />
            </FormField>
            <FormField label="Ville">
              <input value={profile.city} onChange={(e) => set('city', e.target.value)} autoComplete="address-level2" />
            </FormField>
            <FormField label="Code postal">
              <input value={profile.postalCode} onChange={(e) => set('postalCode', e.target.value)} autoComplete="postal-code" />
            </FormField>
            <FormField label="Région">
              <input value={profile.region} onChange={(e) => set('region', e.target.value)} autoComplete="address-level1" />
            </FormField>
            <FormField label="Pays">
              <input value={profile.country} onChange={(e) => set('country', e.target.value)} autoComplete="country-name" />
            </FormField>
            <FormField label="Code pays">
              <input maxLength={2} value={profile.countryCode} onChange={(e) => set('countryCode', e.target.value.toUpperCase())} autoComplete="country" />
            </FormField>
          </div>
        </Card>

        <Card>
          <h2 className={styles.sectionTitle}>Profil professionnel</h2>
          <div className={styles.formGrid}>
            <FormField label="Poste actuel">
              <input value={profile.currentJobTitle} onChange={(e) => set('currentJobTitle', e.target.value)} />
            </FormField>
            <FormField label="Années d’expérience">
              <input type="number" min={0} value={profile.yearsOfExperience} onChange={(e) => set('yearsOfExperience', Number(e.target.value))} />
            </FormField>
            <FormField label="LinkedIn">
              <input value={profile.linkedinUrl ?? ''} onChange={(e) => set('linkedinUrl', e.target.value)} />
            </FormField>
            <FormField label="GitHub">
              <input value={profile.githubUrl ?? ''} onChange={(e) => set('githubUrl', e.target.value)} />
            </FormField>
            <FormField label="Portfolio">
              <input value={profile.portfolioUrl ?? ''} onChange={(e) => set('portfolioUrl', e.target.value)} />
            </FormField>
            <div className={styles.fullWidth}>
              <FormField label="Autres URLs professionnelles (une par ligne)">
                <textarea value={profile.professionalUrls.join('\n')} onChange={(e) => set('professionalUrls', splitLines(e.target.value))} />
              </FormField>
            </div>
            <div className={styles.fullWidth}>
              <FormField label="Expérience par technologie (Technologie: années)">
                <textarea
                  value={technologyExperience}
                  onChange={(event) => {
                    const entries = splitLines(event.target.value).map((line) => {
                      const [technology, years] = line.split(':');
                      return [technology?.trim() ?? '', Math.max(0, Number(years?.trim() ?? 0))] as const;
                    }).filter(([technology]) => technology !== '');
                    set('technologyExperience', Object.fromEntries(entries));
                  }}
                />
              </FormField>
            </div>
            <div className={styles.fullWidth}>
              <FormField label="Langues (une ligne par langue : niveau)">
                <textarea
                  value={profile.languages.map((language) => `${language.language}: ${language.level}`).join('\n')}
                  onChange={(event) => set('languages', splitLines(event.target.value).map((line) => {
                    const [language, ...rest] = line.split(':');
                    return { language: language.trim(), level: rest.join(':').trim() };
                  }))}
                />
              </FormField>
            </div>
          </div>
        </Card>

        <Card>
          <h2 className={styles.sectionTitle}>Préférences de candidature</h2>
          <div className={styles.formGrid}>
            <FormField label="Mobilité">
              <input value={profile.mobility} onChange={(e) => set('mobility', e.target.value)} />
            </FormField>
            <FormField label="Localisations préférées">
              <input value={profile.preferredLocations.join(', ')} onChange={(e) => set('preferredLocations', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} />
            </FormField>
            <FormField label="Autorisation de travail">
              <input value={profile.workAuthorisation} onChange={(e) => set('workAuthorisation', e.target.value)} />
            </FormField>
            <FormField label="Disponibilité">
              <input value={profile.availability} onChange={(e) => set('availability', e.target.value)} />
            </FormField>
            <FormField label="Préavis">
              <input value={profile.noticePeriod} onChange={(e) => set('noticePeriod', e.target.value)} />
            </FormField>
            <FormField label="Préférence télétravail / site">
              <input value={profile.workModePreference} onChange={(e) => set('workModePreference', e.target.value)} />
            </FormField>
            <FormField label="Contrats acceptés">
              <input value={profile.acceptedContracts.join(', ')} onChange={(e) => set('acceptedContracts', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))} />
            </FormField>
            <FormField label="Salaire souhaité (€ brut/an)">
              <input type="number" min={0} value={profile.desiredSalary ?? ''} onChange={(e) => set('desiredSalary', e.target.value === '' ? null : Number(e.target.value))} />
            </FormField>
            <FormField label="TJM souhaité (€)">
              <input type="number" min={0} value={profile.desiredTjm ?? ''} onChange={(e) => set('desiredTjm', e.target.value === '' ? null : Number(e.target.value))} />
            </FormField>
          </div>
        </Card>
      </div>
    </>
  );
}
