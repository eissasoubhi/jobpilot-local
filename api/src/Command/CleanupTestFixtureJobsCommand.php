<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:jobs:cleanup-test-fixtures',
    description: 'Détecte les offres créées par les tests fonctionnels avec des domaines réservés .example et peut les supprimer explicitement.',
)]
final class CleanupTestFixtureJobsCommand extends Command
{
    private const PREVIEW_LIMIT = 30;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply',
            null,
            InputOption::VALUE_NONE,
            'Supprime réellement les offres détectées. Sans cette option, la commande est en lecture seule.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<JobOffer> $offers */
        $offers = $this->entityManager
            ->getRepository(JobOffer::class)
            ->createQueryBuilder('job')
            ->leftJoin('job.occurrences', 'occurrence')
            ->addSelect('occurrence')
            ->getQuery()
            ->getResult();

        $fixtures = array_values(array_filter($offers, $this->isReservedExampleFixture(...)));

        if ($fixtures === []) {
            $output->writeln('<info>Aucune offre de test utilisant un domaine réservé .example n’a été trouvée.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<comment>%d offre(s) de test détectée(s). Aperçu :</comment>',
            count($fixtures),
        ));

        foreach (array_slice($fixtures, 0, self::PREVIEW_LIMIT) as $offer) {
            $output->writeln(sprintf(
                '  #%d — %s — %s — %s',
                $offer->getId() ?? 0,
                $offer->getSource(),
                $offer->getCompany(),
                $offer->getSourceUrl() ?? '(URL portée par une occurrence)',
            ));
        }

        if (count($fixtures) > self::PREVIEW_LIMIT) {
            $output->writeln(sprintf('  … et %d autre(s).', count($fixtures) - self::PREVIEW_LIMIT));
        }

        if (!$input->getOption('apply')) {
            $output->writeln('<info>Mode lecture seule : aucune donnée supprimée. Relance avec --apply après vérification de l’aperçu.</info>');

            return Command::SUCCESS;
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            foreach ($fixtures as $offer) {
                $this->entityManager->remove($offer);
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $output->writeln(sprintf(
            '<info>%d offre(s) de test supprimée(s). Les suppressions liées restent gérées par les contraintes de la base.</info>',
            count($fixtures),
        ));

        return Command::SUCCESS;
    }

    private function isReservedExampleFixture(JobOffer $offer): bool
    {
        if ($this->isReservedExampleUrl($offer->getSourceUrl())) {
            return true;
        }

        foreach ($offer->getOccurrences() as $occurrence) {
            if ($occurrence instanceof JobSourceOccurrence && $this->isReservedExampleUrl($occurrence->getSourceUrl())) {
                return true;
            }
        }

        return false;
    }

    private function isReservedExampleUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));

        return $host === 'example'
            || str_ends_with($host, '.example');
    }
}
