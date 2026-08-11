<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReusableAnswerLibraryTest extends WebTestCase
{
    public function testDefaultAnswersAreResolvedFromCandidateProfile(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/reusable-answers/resolved');
        self::assertResponseIsSuccessful();

        $payload = $this->decode($client);
        self::assertSame(1, $payload['schemaVersion']);

        $answers = [];
        foreach ($payload['answers'] as $answer) {
            $answers[$answer['key']] = $answer;
        }

        self::assertArrayHasKey('availability', $answers);
        self::assertSame('Immédiatement', $answers['availability']['resolved']['fr']);
        self::assertTrue($answers['availability']['eligibleForAutomaticFill']);

        self::assertArrayHasKey('years_experience', $answers);
        self::assertSame('11', $answers['years_experience']['resolved']['en']);

        self::assertArrayHasKey('work_authorisation', $answers);
        self::assertSame('Salarié', $answers['work_authorisation']['resolved']['fr']);
        self::assertTrue($answers['work_authorisation']['sensitive']);
        self::assertFalse($answers['work_authorisation']['autoFillAllowed']);
        self::assertFalse($answers['work_authorisation']['eligibleForAutomaticFill']);
    }

    public function testCustomAnswerCanBeCreatedUpdatedResolvedAndDeleted(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/reusable-answers', [
            'key' => 'preferred_contract_test',
            'label' => 'Contrat préféré test',
            'category' => 'CUSTOM',
            'valueSource' => 'STATIC',
            'answerType' => 'CHOICE',
            'answerFr' => 'CDI',
            'answerEn' => 'Permanent contract',
            'questionPatterns' => [
                'fr' => ['Quel type de contrat recherchez-vous ?'],
                'en' => ['What type of contract are you looking for?'],
            ],
            'enabled' => true,
            'sensitive' => false,
            'autoFillAllowed' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client);
        self::assertSame('preferred_contract_test', $created['key']);
        self::assertSame('CDI', $created['answerFr']);

        $id = (int) $created['id'];
        $client->jsonRequest('PATCH', sprintf('/api/reusable-answers/%d', $id), [
            'sensitive' => true,
        ]);
        self::assertResponseIsSuccessful();
        $updated = $this->decode($client);
        self::assertTrue($updated['sensitive']);
        self::assertFalse($updated['autoFillAllowed']);

        $client->request('GET', '/api/reusable-answers/resolved');
        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);

        $resolved = null;
        foreach ($payload['answers'] as $answer) {
            if (($answer['key'] ?? null) === 'preferred_contract_test') {
                $resolved = $answer;
                break;
            }
        }

        self::assertIsArray($resolved);
        self::assertSame('CDI', $resolved['resolved']['fr']);
        self::assertSame('Permanent contract', $resolved['resolved']['en']);
        self::assertFalse($resolved['eligibleForAutomaticFill']);

        $client->request('DELETE', sprintf('/api/reusable-answers/%d', $id));
        self::assertResponseStatusCodeSame(204);
    }

    /** @return array<string|int, mixed> */
    private function decode(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
