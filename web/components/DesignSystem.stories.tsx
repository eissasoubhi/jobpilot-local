import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import {
  Button,
  Card,
  DataList,
  DataListItem,
  DataToolbar,
  Empty,
  ErrorBox,
  FormField,
  InlineFeedback,
  ProgressBar,
} from './UI';
import styles from './DesignSystem.stories.module.css';

const meta = {
  title: 'Design System/Core contracts',
  parameters: {
    docs: {
      description: {
        component:
          'Shared JobPilot V1 primitives and visual-language contracts. Core stays calm and productive; Bento is reserved for dashboards, Data-dense for operational lists, Editorial for long reading, and AI-native/Aurora for genuine AI insight only.',
      },
    },
  },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const VisualLanguageMap: Story = {
  name: 'Visual language map',
  render: () => (
    <div>
      <div className={styles.languageIntro}>
        <strong>Calm by default, powerful on demand.</strong>
        <span>
          JobPilot garde une base visuelle commune. Les styles ci-dessous ne sont pas cinq thèmes concurrents :
          chacun répond à un type de tâche précis et réutilise les mêmes tokens, composants et règles d’accessibilité.
        </span>
      </div>

      <div className={styles.languageGrid}>
        <section className={styles.languageCard}>
          <span className={styles.languageLabel}>Core · partout</span>
          <h3>Minimal / Productivity</h3>
          <p>Base de la navigation, des formulaires, actions et feedbacks. Peu de décoration, hiérarchie nette.</p>
          <div className={styles.coreDemo}>
            <Button size="small">Action principale</Button>
            <Button size="small" variant="secondary">Secondaire</Button>
            <Button size="small" variant="subtle">Discrète</Button>
          </div>
        </section>

        <section className={styles.languageCard}>
          <span className={styles.languageLabel}>Dashboard</span>
          <h3>Bento</h3>
          <p>Résumé, KPI et priorités. Les cartes organisent l’information sans transformer chaque écran en mosaïque.</p>
          <div className={styles.bentoDemo} aria-label="Exemple de composition Bento">
            <div className={styles.bentoTile}><span>Offres adaptées</span><strong>23</strong></div>
            <div className={styles.bentoTile}><span>Entretiens</span><strong>4</strong></div>
            <div className={styles.bentoTile}><span>Score moyen</span><strong>82</strong></div>
          </div>
        </section>

        <section className={styles.languageCard}>
          <span className={styles.languageLabel}>Offres · candidatures · CRM</span>
          <h3>Data-dense</h3>
          <p>Densité maîtrisée, lignes compactes, statut et action visibles. Priorité au scan rapide et aux décisions répétées.</p>
          <div className={styles.dataDemo} role="table" aria-label="Exemple de liste opérationnelle">
            <div className={styles.dataRow} role="row">
              <strong>Senior Symfony / React</strong><span>Freelance · Paris</span><span>92/100</span>
            </div>
            <div className={styles.dataRow} role="row">
              <strong>Full-stack PHP</strong><span>Remote</span><span>87/100</span>
            </div>
          </div>
        </section>

        <section className={styles.languageCard}>
          <span className={styles.languageLabel}>Détail d’offre</span>
          <h3>Editorial</h3>
          <p>Pour les contenus longs : largeur de lecture contrôlée, rythme vertical et typographie avant les effets visuels.</p>
          <div className={styles.editorialDemo}>
            La mission recherche une expérience solide en Symfony et React. Le détail conserve une lecture confortable,
            tandis que les métadonnées, le score et les actions restent distincts du corps de l’offre.
          </div>
        </section>

        <section className={`${styles.languageCard} ${styles.aiCard} ${styles.fullWidth}`}>
          <span className={styles.aiLabel}>IA · usage ciblé</span>
          <h3>AI-native / Aurora</h3>
          <p>
            Réservé au matching, aux recommandations et aux explications générées par IA. Aurora signale une capacité IA ;
            ce n’est jamais le fond visuel global du produit.
          </p>
          <div className={styles.aiScore}>
            <strong>88 %</strong>
            <span>Très bon match · Symfony · React · e-commerce</span>
          </div>
          <div className={styles.coreDemo}>
            <Button size="small">Adapter mon CV</Button>
            <Button size="small" variant="secondary">Pourquoi 88 % ?</Button>
          </div>
        </section>
      </div>
    </div>
  ),
};

export const DecisionFirstPattern: Story = {
  name: 'Decision-first hierarchy',
  render: () => (
    <Card>
      <DataToolbar actions={<Button size="small">Action principale</Button>}>
        <div>
          <strong>Décision à prendre</strong>
          <div className="muted">Le résumé utile arrive avant le détail.</div>
        </div>
      </DataToolbar>

      <div style={{ display: 'grid', gap: '0.75rem', marginTop: '1rem', maxWidth: 720 }}>
        <InlineFeedback tone="warning">
          1 point à vérifier · aucun blocage détecté.
        </InlineFeedback>
        <div>
          <strong>Résumé</strong>
          <p>Les informations essentielles, les manques bloquants et le prochain geste doivent être scannables en quelques secondes.</p>
        </div>
        <details>
          <summary>Voir le détail</summary>
          <p>Les preuves secondaires, le texte long et les diagnostics restent disponibles sans concurrencer la décision principale.</p>
        </details>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
          <Button size="small" variant="secondary">Action secondaire</Button>
          <Button size="small" variant="subtle">Autre option</Button>
        </div>
      </div>
    </Card>
  ),
};

export const Buttons: Story = {
  render: () => (
    <Card>
      <DataToolbar actions={<Button variant="subtle">Action discrète</Button>}>
        <strong>Actions partagées</strong>
      </DataToolbar>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', marginTop: '1rem' }}>
        <Button>Primaire</Button>
        <Button variant="secondary">Secondaire</Button>
        <Button variant="subtle">Subtile</Button>
        <Button variant="danger">Supprimer</Button>
        <Button loading>Chargement</Button>
        <Button disabled>Désactivée</Button>
      </div>
    </Card>
  ),
};

export const FeedbackStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '0.75rem', maxWidth: 720 }}>
      <InlineFeedback>Information contextuelle.</InlineFeedback>
      <InlineFeedback tone="success">Modification enregistrée.</InlineFeedback>
      <InlineFeedback tone="warning">Une vérification est nécessaire avant de continuer.</InlineFeedback>
      <ErrorBox
        title="Données indisponibles"
        message="Impossible de charger les données."
        impact="Le contenu affiché n’a pas été modifié."
        details="Le service local n’a pas répondu à la dernière requête."
        onRetry={() => undefined}
      />
      <Empty>Aucun élément à afficher.</Empty>
    </div>
  ),
};

export const DenseListAndToolbar: Story = {
  render: () => (
    <Card>
      <DataToolbar actions={<Button size="small">Actualiser</Button>}>
        <div>
          <strong>Candidatures</strong>
          <div>3 éléments à examiner</div>
        </div>
      </DataToolbar>
      <DataList aria-label="Candidatures à examiner" style={{ marginTop: '1rem' }}>
        <DataListItem>
          <strong>Senior PHP / Symfony</strong>
          <div>Entreprise Alpha · Paris</div>
        </DataListItem>
        <DataListItem>
          <strong>Full-stack React / PHP</strong>
          <div>Entreprise Beta · Remote</div>
        </DataListItem>
        <DataListItem>
          <strong>Software Engineer</strong>
          <div>Entreprise Gamma · Hybride</div>
        </DataListItem>
      </DataList>
    </Card>
  ),
};

export const AccessibleFormStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '1rem', maxWidth: 520 }}>
      <FormField label="Nom complet" hint="Utilisé dans les candidatures.">
        <input type="text" defaultValue="Aissa Soubhi" />
      </FormField>
      <FormField label="E-mail" error="Adresse e-mail invalide.">
        <input type="email" defaultValue="adresse-invalide" />
      </FormField>
      <FormField label="LinkedIn" success="Lien vérifié.">
        <input type="url" defaultValue="https://www.linkedin.com/in/example" />
      </FormField>
    </div>
  ),
};

export const ProgressStates: Story = {
  render: () => (
    <div style={{ display: 'grid', gap: '1rem', maxWidth: 620 }}>
      <ProgressBar value={25} label="Synchronisation" valueText="25 % terminé" />
      <ProgressBar value={64} label="Objectif candidatures" valueText="64 % atteint" tone="good" />
      <ProgressBar value={90} label="Quota" valueText="90 % utilisé" tone="warn" />
    </div>
  ),
};