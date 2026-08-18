<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CandidateProfileAutofillTest extends WebTestCase
{
    public function testProfileCanStoreAndExposeAutofillFields(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', '/api/profile', [
            'fullName' => 'Demo Candidate',
            'firstName' => 'Demo',
            'lastName' => 'Candidate',
            'addressLine1' => '10 rue de Test',
            'addressLine2' => '',
            'city' => 'Paris',
            'postalCode' => '75000',
            'region' => 'Île-de-France',
            'country' => 'France',
            'countryCode' => 'fr',
            'currentJobTitle' => 'Senior Symfony Developer',
            'preferredLocations' => ['Île-de-France', 'Remote'],
            'technologyExperience' => [
                'Symfony' => 8,
                'React' => 5,
            ],
            'desiredSalary' => 55_000,
            'desiredTjm' => 500,
            'githubUrl' => 'https://github.com/example',
            'professionalUrls' => ['https://example.test/profile'],
        ]);

        self::assertResponseIsSuccessful();
        $profile = $this->decodeResponse($client);
        self::assertSame('Demo', $profile['firstName']);
        self::assertSame('Candidate', $profile['lastName']);
        self::assertSame('FR', $profile['countryCode']);
        self::assertSame(8, $profile['technologyExperience']['Symfony']);
        self::assertSame(500, $profile['desiredTjm']);
        self::assertNull($profile['addressLine2']);

        $client->request('GET', '/api/profile/autofill');
        self::assertResponseIsSuccessful();
        $autofill = $this->decodeResponse($client);

        self::assertSame(1, $autofill['schemaVersion']);
        self::assertSame('Demo', $autofill['identity']['firstName']);
        self::assertSame('10 rue de Test', $autofill['address']['line1']);
        self::assertSame('Senior Symfony Developer', $autofill['professional']['currentJobTitle']);
        self::assertSame(8, $autofill['professional']['technologyExperience']['Symfony']);
        self::assertSame(['Île-de-France', 'Remote'], $autofill['preferences']['preferredLocations']);
        self::assertSame(55_000, $autofill['preferences']['desiredSalary']);
        self::assertArrayHasKey('workAuthorisation', $autofill['screening']);
    }

    /** @return array<string|int, mixed> */
    private function decodeResponse(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
