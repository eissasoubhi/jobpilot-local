<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\AutomaticSubmissionService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchPreferenceGuardsTest extends WebTestCase
{
    public function testManualPreparationRejectsOfferOutsideCurrentProfilePreferences(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $data = $container->get(LocalDataService::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(LocalDataService::class, $data);

        $profile = $data->profile();
        $originalProfile = $profile->toArray();
        $job = (new JobOffer())->fill([
            'source' => 'Preference guard test',
            'title' => 'Senior Symfony CDI',
            'company' => 'JobPilot',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Offre créée pour vérifier le garde-fou de préparation.',
            'status' => 'DISCOVERED',
        ]);

        try {
            $profile->fill([
                'acceptedContracts' => ['Freelance'],
                'workModePreference' => 'Aucune préférence',
            ]);
            $em->persist($job);
            $em->flush();

            self::assertNotNull($job->getId());
            $client->request('POST', sprintf('/api/jobs/%d/prepare', $job->getId()));

            self::assertResponseStatusCodeSame(422);
            $payload = $this->decode($client);
            self::assertSame(
                'Cette offre ne correspond pas aux préférences de recherche du profil.',
                $payload['error'],
            );
            self::assertNotEmpty($payload['reasons']);
            self::assertStringContainsString('contrat', implode(' ', $payload['reasons']));
        } finally {
            $profile->fill($originalProfile);
            $em->remove($job);
            $em->flush();
        }
    }

    public function testAutomaticSubmissionRechecksCurrentProfilePreferencesBeforeSending(): void
    {
        static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $data = $container->get(LocalDataService::class);
        $submission = $container->get(AutomaticSubmissionService::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(LocalDataService::class, $data);
        self::assertInstanceOf(AutomaticSubmissionService::class, $submission);

        $profile = $data->profile();
        $originalProfile = $profile->toArray();

        try {
            $profile->fill([
                'acceptedContracts' => ['Freelance'],
                'workModePreference' => 'Aucune préférence',
            ]);
            $em->flush();

            $job = (new JobOffer())->fill([
                'source' => 'Preference guard test',
                'title' => 'Senior Symfony CDI',
                'company' => 'JobPilot',
                'contractType' => 'CDI',
                'workMode' => 'Hybride',
                'description' => 'Offre créée pour vérifier le garde-fou avant envoi.',
                'status' => 'PREPARED',
            ]);
            $application = (new Application($job))->fill(['status' => 'READY_TO_SUBMIT']);
            $settings = (new UserSettings())->fill([
                'autoSubmitEnabled' => true,
                'autoSubmitThreshold' => 1,
                'autoSubmitDailyLimit' => 5,
            ]);

            self::assertSame([
                'status' => 'skipped',
                'reason' => 'profile_preferences_mismatch',
            ], $submission->submitIfEligible($application, $settings));
            self::assertSame('READY_TO_SUBMIT', $application->getStatus());
        } finally {
            $profile->fill($originalProfile);
            $em->flush();
        }
    }

    public function testIndependentContractAliasUsesFreelanceTjmRules(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $data = $container->get(LocalDataService::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(LocalDataService::class, $data);

        $profile = $data->profile();
        $originalProfile = $profile->toArray();
        $expectedTjm = min(540, $data->settings()->getMaximumTjm());
        $jobId = null;

        try {
            $profile->fill([
                'acceptedContracts' => ['Freelance'],
                'workModePreference' => 'Aucune préférence',
            ]);
            $em->flush();

            $client->jsonRequest('POST', '/api/jobs', [
                'source' => 'Preference guard test',
                'sourceUrl' => 'https://example.test/jobs/independent-'.bin2hex(random_bytes(6)),
                'title' => 'Senior Symfony indépendant',
                'company' => 'JobPilot',
                'location' => 'Paris',
                'contractType' => 'Indépendant',
                'workMode' => 'Hybride',
                'description' => 'Mission indépendante Symfony React avec TJM annoncé.',
                'tjmMin' => 480,
                'tjmMax' => 600,
            ]);

            self::assertResponseStatusCodeSame(201);
            $payload = $this->decode($client);
            $jobId = (int) $payload['id'];
            self::assertSame('PREPARED', $payload['status']);
            self::assertSame($expectedTjm, $payload['proposedTjm']);
        } finally {
            if (is_int($jobId) && $jobId > 0) {
                $client->request('DELETE', sprintf('/api/jobs/%d', $jobId));
            }
            $profile->fill($originalProfile);
            $em->flush();
        }
    }

    /** @return array<string|int, mixed> */
    private function decode(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
