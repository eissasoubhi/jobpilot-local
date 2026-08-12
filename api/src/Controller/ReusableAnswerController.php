<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ReusableAnswer;
use App\Service\LocalDataService;
use App\Service\ReusableAnswerMatcher;
use App\Service\ReusableAnswerResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reusable-answers')]
final class ReusableAnswerController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private ReusableAnswerResolver $resolver,
        private ReusableAnswerMatcher $matcher,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $answers = $this->em->getRepository(ReusableAnswer::class)->findBy([], ['category' => 'ASC', 'label' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (ReusableAnswer $answer): array => $answer->toArray(),
            $answers,
        ));
    }

    #[Route('/resolved', methods: ['GET'])]
    public function resolved(): JsonResponse
    {
        $profile = $this->data->profile();
        $answers = $this->em->getRepository(ReusableAnswer::class)->findBy([], ['category' => 'ASC', 'label' => 'ASC']);

        return new JsonResponse([
            'schemaVersion' => 1,
            'answers' => array_map(
                fn (ReusableAnswer $answer): array => $this->resolver->resolve($answer, $profile),
                $answers,
            ),
        ]);
    }

    #[Route('/match', methods: ['GET'])]
    public function match(Request $request): JsonResponse
    {
        $question = trim((string) $request->query->get('question', ''));
        if ($question === '' || mb_strlen($question) > 1000) {
            return new JsonResponse(['error' => 'La question est obligatoire et limitée à 1000 caractères.'], 400);
        }

        $language = strtolower(trim((string) $request->query->get('language', 'fr')));
        $profile = $this->data->profile();
        $answers = $this->em->getRepository(ReusableAnswer::class)->findAll();
        $matches = $this->matcher->match($question, $language, $answers);

        return new JsonResponse([
            'schemaVersion' => 1,
            'question' => $question,
            'language' => in_array($language, ['fr', 'en'], true) ? $language : 'fr',
            'matches' => array_map(
                fn (array $match): array => [
                    'score' => $match['score'],
                    'matchedPattern' => $match['matchedPattern'],
                    'answer' => $this->resolver->resolve($match['answer'], $profile),
                ],
                $matches,
            ),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        try {
            $answer = new ReusableAnswer(
                (string) ($payload['key'] ?? ''),
                (string) ($payload['label'] ?? ''),
            );
            $answer->fill($payload);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        if ($this->em->getRepository(ReusableAnswer::class)->findOneBy(['key' => $answer->getKey()]) !== null) {
            return new JsonResponse(['error' => 'Cette clé de réponse existe déjà.'], 409);
        }

        $this->em->persist($answer);
        $this->em->flush();

        return new JsonResponse($answer->toArray(), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $answer = $this->em->find(ReusableAnswer::class, $id);
        if (!$answer instanceof ReusableAnswer) {
            return new JsonResponse(['error' => 'Réponse automatique introuvable.'], 404);
        }

        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        try {
            $answer->fill($payload);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $this->em->flush();

        return new JsonResponse($answer->toArray());
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $answer = $this->em->find(ReusableAnswer::class, $id);
        if (!$answer instanceof ReusableAnswer) {
            return new JsonResponse(['error' => 'Réponse automatique introuvable.'], 404);
        }

        $this->em->remove($answer);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function payload(Request $request): array|JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        return is_array($payload)
            ? $payload
            : new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
    }
}
