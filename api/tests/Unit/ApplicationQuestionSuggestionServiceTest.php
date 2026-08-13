<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\ReusableAnswer;
use App\Service\Ai\ApplicationQuestionAiSuggesterInterface;
use App\Service\ApplicationQuestionSuggestionService;
use App\Service\ReusableAnswerMatcher;
use App\Service\ReusableAnswerResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ApplicationQuestionSuggestionServiceTest extends TestCase
{
    public function testReusableAnswerWinsBeforeAi(): void
    {
        $answer = (new ReusableAnswer('availability_test', 'Disponibilité test'))->fill([
            'valueSource' => 'PROFILE',
            'profilePath' => 'preferences.availability',
            'questionPatterns' => [
                'fr' => ['Quand pouvez-vous commencer ?'],
                'en' => ['When can you start?'],
            ],
            'enabled' => true,
            'autoFillAllowed' => true,
        ]);
        $ai = new FakeQuestionSuggester([
            'canAnswer' => true,
            'answer' => 'Cette réponse ne doit jamais être utilisée.',
            'confidence' => 1.0,
            'usedFacts' => [],
            'model' => 'fake',
        ]);

        $result = $this->service([$answer], $ai)->suggest(
            $this->job(),
            $this->profile(),
            'Quand pouvez-vous commencer ?',
            'fr',
            600,
        );

        self::assertSame('SUGGESTED', $result['status']);
        self::assertSame('reusable', $result['source']);
        self::assertSame('Immédiatement', $result['suggestion']);
        self::assertFalse($ai->called);
    }

    public function testSensitiveReusableQuestionNeverFallsThroughToAi(): void
    {
        $answer = (new ReusableAnswer('work_auth_test', 'Autorisation travail test'))->fill([
            'valueSource' => 'PROFILE',
            'profilePath' => 'screening.workAuthorisation',
            'questionPatterns' => [
                'fr' => ['Êtes-vous autorisé à travailler en France ?'],
                'en' => ['Are you authorized to work in France?'],
            ],
            'enabled' => true,
            'sensitive' => true,
            'autoFillAllowed' => false,
        ]);
        $ai = new FakeQuestionSuggester([
            'canAnswer' => true,
            'answer' => 'Ne doit pas être appelé.',
            'confidence' => 1.0,
            'usedFacts' => [],
            'model' => 'fake',
        ]);

        $result = $this->service([$answer], $ai)->suggest(
            $this->job(),
            $this->profile(),
            'Êtes-vous autorisé à travailler en France ?',
            'fr',
            600,
        );

        self::assertSame('MANUAL_REVIEW', $result['status']);
        self::assertSame('reusable', $result['source']);
        self::assertFalse($ai->called);
    }

    public function testSensitivePolicyBlocksAiEvenWithoutReusableAnswer(): void
    {
        $ai = new FakeQuestionSuggester([
            'canAnswer' => true,
            'answer' => 'Ne doit pas être appelé.',
            'confidence' => 1.0,
            'usedFacts' => [],
            'model' => 'fake',
        ]);

        $result = $this->service([], $ai)->suggest(
            $this->job(),
            $this->profile(),
            'What is your date of birth?',
            'en',
            600,
        );

        self::assertSame('MANUAL_REVIEW', $result['status']);
        self::assertSame('policy', $result['source']);
        self::assertSame('demographic', $result['reason']);
        self::assertFalse($ai->called);
    }

    public function testSpecificQuestionCanReceiveGroundedAiSuggestion(): void
    {
        $ai = new FakeQuestionSuggester([
            'canAnswer' => true,
            'answer' => 'Le poste correspond à mon expérience Symfony et React, avec une forte dimension produit.',
            'confidence' => 0.91,
            'usedFacts' => ['8 ans Symfony', '5 ans React'],
            'model' => 'gemini-test',
        ]);

        $result = $this->service([], $ai)->suggest(
            $this->job(),
            $this->profile(),
            'Pourquoi souhaitez-vous rejoindre cette équipe sur ce poste ?',
            'fr',
            600,
        );

        self::assertTrue($ai->called);
        self::assertSame('SUGGESTED', $result['status']);
        self::assertSame('ai', $result['source']);
        self::assertSame(0.91, $result['confidence']);
        self::assertTrue($result['requiresExplicitInsert']);
        self::assertStringContainsString('Symfony', $result['suggestion']);
    }

    public function testAiCanRefuseWhenGroundedDataIsInsufficient(): void
    {
        $ai = new FakeQuestionSuggester([
            'canAnswer' => false,
            'answer' => '',
            'confidence' => 0.2,
            'usedFacts' => [],
            'model' => 'gemini-test',
        ]);

        $result = $this->service([], $ai)->suggest(
            $this->job(),
            $this->profile(),
            'Décrivez votre expérience directe dans notre secteur très spécialisé.',
            'fr',
            600,
        );

        self::assertTrue($ai->called);
        self::assertSame('INSUFFICIENT_GROUNDED_DATA', $result['status']);
        self::assertArrayNotHasKey('suggestion', $result);
    }

    /** @param list<ReusableAnswer> $answers */
    private function service(array $answers, FakeQuestionSuggester $ai): ApplicationQuestionSuggestionService
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn($answers);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(ReusableAnswer::class)->willReturn($repository);

        return new ApplicationQuestionSuggestionService(
            $em,
            new ReusableAnswerMatcher(),
            new ReusableAnswerResolver(),
            $ai,
        );
    }

    private function profile(): CandidateProfile
    {
        return (new CandidateProfile())->fill([
            'firstName' => 'Aissa',
            'lastName' => 'SOUBHI',
            'availability' => 'Immédiatement',
            'workAuthorisation' => 'Salarié',
            'yearsOfExperience' => 11,
            'technologyExperience' => [
                'Symfony' => 8,
                'React' => 5,
            ],
        ]);
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'source' => 'Test',
            'sourceCode' => 'test',
            'title' => 'Senior Symfony React Developer',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Poste produit avec PHP, Symfony, React, TypeScript et API Platform.',
        ]);
    }
}

final class FakeQuestionSuggester implements ApplicationQuestionAiSuggesterInterface
{
    public bool $called = false;

    /** @param array{canAnswer: bool, answer: string, confidence: float, usedFacts: list<string>, model: string}|null $result */
    public function __construct(private readonly ?array $result)
    {
    }

    public function suggest(
        JobOffer $job,
        CandidateProfile $profile,
        string $question,
        string $language,
        int $maxLength,
    ): ?array {
        $this->called = true;

        return $this->result;
    }
}
