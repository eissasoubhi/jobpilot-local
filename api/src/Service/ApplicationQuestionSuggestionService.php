<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\ReusableAnswer;
use App\Service\Ai\ApplicationQuestionAiSuggesterInterface;
use App\Service\Ai\ConfiguredGeminiApplicationQuestionSuggester;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ApplicationQuestionSuggestionService
{
    private const REUSABLE_MATCH_THRESHOLD = 0.72;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReusableAnswerMatcher $matcher,
        private readonly ReusableAnswerResolver $resolver,
        #[Autowire(service: ConfiguredGeminiApplicationQuestionSuggester::class)]
        private readonly ApplicationQuestionAiSuggesterInterface $aiSuggester,
    ) {
    }

    /** @return array<string, mixed> */
    public function suggest(
        JobOffer $job,
        CandidateProfile $profile,
        string $question,
        string $language,
        int $maxLength,
    ): array {
        $question = trim(mb_substr($question, 0, 2000));
        $language = strtolower(trim($language)) === 'en' ? 'en' : 'fr';
        $maxLength = max(80, min(1500, $maxLength));

        if ($question === '') {
            return ['status' => 'INVALID', 'source' => 'none', 'message' => 'La question est vide.'];
        }

        $answers = $this->em->getRepository(ReusableAnswer::class)->findAll();
        $matches = $this->matcher->match($question, $language, $answers);
        $best = $matches[0] ?? null;

        if (is_array($best) && (float) ($best['score'] ?? 0.0) >= self::REUSABLE_MATCH_THRESHOLD) {
            /** @var ReusableAnswer $answer */
            $answer = $best['answer'];
            $resolved = $this->resolver->resolve($answer, $profile);
            $value = trim((string) ($resolved['resolved'][$language] ?? $resolved['resolved']['fr'] ?? $resolved['resolved']['en'] ?? ''));

            if (($resolved['sensitive'] ?? false) === true || ($resolved['autoFillAllowed'] ?? false) !== true) {
                return [
                    'status' => 'MANUAL_REVIEW',
                    'source' => 'reusable',
                    'reason' => 'sensitive-or-explicit-review',
                    'reusableAnswerKey' => $answer->getKey(),
                    'matchScore' => (float) $best['score'],
                    'matchedPattern' => (string) ($best['matchedPattern'] ?? ''),
                    'message' => 'Cette question correspond à une réponse sensible ou nécessitant une validation explicite.',
                ];
            }

            if (($resolved['eligibleForAutomaticFill'] ?? false) !== true || $value === '') {
                return [
                    'status' => 'MISSING_PROFILE_DATA',
                    'source' => 'reusable',
                    'reason' => 'known-answer-without-value',
                    'reusableAnswerKey' => $answer->getKey(),
                    'matchScore' => (float) $best['score'],
                    'matchedPattern' => (string) ($best['matchedPattern'] ?? ''),
                    'message' => 'La question est connue, mais la valeur correspondante manque dans le profil.',
                ];
            }

            return [
                'status' => 'SUGGESTED',
                'source' => 'reusable',
                'suggestion' => mb_substr($value, 0, $maxLength),
                'confidence' => min(1.0, (float) $best['score']),
                'reusableAnswerKey' => $answer->getKey(),
                'matchScore' => (float) $best['score'],
                'matchedPattern' => (string) ($best['matchedPattern'] ?? ''),
                'requiresExplicitInsert' => true,
            ];
        }

        $manualReason = $this->manualOnlyReason($question);
        if ($manualReason !== null) {
            return [
                'status' => 'MANUAL_REVIEW',
                'source' => 'policy',
                'reason' => $manualReason,
                'message' => 'JobPilot ne génère pas de réponse IA pour cette catégorie de question.',
            ];
        }

        $suggestion = $this->aiSuggester->suggest($job, $profile, $question, $language, $maxLength);
        if ($suggestion === null) {
            return [
                'status' => 'AI_UNAVAILABLE',
                'source' => 'ai',
                'message' => 'La suggestion IA est indisponible ou le quota local est atteint.',
            ];
        }

        if (($suggestion['canAnswer'] ?? false) !== true || trim((string) ($suggestion['answer'] ?? '')) === '') {
            return [
                'status' => 'INSUFFICIENT_GROUNDED_DATA',
                'source' => 'ai',
                'confidence' => (float) ($suggestion['confidence'] ?? 0.0),
                'usedFacts' => $suggestion['usedFacts'] ?? [],
                'model' => $suggestion['model'] ?? null,
                'message' => 'Le profil ne contient pas assez d’éléments fiables pour proposer une réponse sans inventer.',
            ];
        }

        return [
            'status' => 'SUGGESTED',
            'source' => 'ai',
            'suggestion' => mb_substr(trim((string) $suggestion['answer']), 0, $maxLength),
            'confidence' => (float) ($suggestion['confidence'] ?? 0.0),
            'usedFacts' => $suggestion['usedFacts'] ?? [],
            'model' => $suggestion['model'] ?? null,
            'requiresExplicitInsert' => true,
        ];
    }

    private function manualOnlyReason(string $question): ?string
    {
        $normalized = mb_strtolower($question);
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        $categories = [
            'work-authorization' => [
                'work authorization', 'work authorisation', 'authorized to work', 'authorised to work',
                'autorisation de travail', 'autorise a travailler', 'droit de travailler', 'work permit', 'permis de travail',
            ],
            'visa-sponsorship' => [
                'visa sponsorship', 'require sponsorship', 'need sponsorship', 'sponsorship', 'sponsor visa',
                'parrainage visa', 'visa de travail',
            ],
            'compensation' => [
                'salary', 'compensation', 'expected pay', 'desired pay', 'daily rate', 'day rate',
                'salaire', 'remuneration', 'pretentions salariales', 'tjm', 'tarif journalier',
            ],
            'demographic' => [
                'date of birth', 'birth date', 'age', 'gender', 'sex', 'sexual orientation', 'race', 'ethnicity',
                'religion', 'marital status', 'veteran', 'disability', 'handicap', 'situation familiale', 'origine ethnique',
                'sexe', 'genre', 'ancien combattant',
            ],
            'criminal-health' => [
                'criminal record', 'background check', 'conviction', 'casier judiciaire', 'condamnation',
                'medical condition', 'health condition', 'etat de sante', 'sante',
            ],
        ];

        foreach ($categories as $reason => $terms) {
            foreach ($terms as $term) {
                $term = strtolower($term);
                if ($normalized === $term || str_contains(' '.$normalized.' ', ' '.$term.' ')) {
                    return $reason;
                }
            }
        }

        return null;
    }
}
