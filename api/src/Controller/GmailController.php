<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Service\ApplicationEmailFactory;
use App\Service\GmailService;
use App\Service\GmailTokenStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/gmail')]
final class GmailController
{
    public function __construct(
        private GmailService $gmail,
        private GmailTokenStore $store,
        private ApplicationEmailFactory $emailFactory,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'connected' => $this->store->isConnected(),
            'sendPermission' => $this->gmail->hasSendPermission(),
            ...$this->gmail->configuration(),
        ]);
    }

    #[Route('/start', methods: ['GET'])]
    public function start(): RedirectResponse
    {
        try {
            return new RedirectResponse($this->gmail->authorizationUrl());
        } catch (\Throwable $error) {
            return $this->settingsRedirect(['gmail_error' => $error->getMessage()]);
        }
    }

    #[Route('/callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $googleError = trim((string) $request->query->get('error'));
        if ($googleError !== '') {
            $description = trim((string) $request->query->get('error_description'));

            return $this->settingsRedirect([
                'gmail_error' => $description !== ''
                    ? $description
                    : 'Google a refusé ou annulé l’autorisation Gmail.',
            ]);
        }

        try {
            $this->gmail->handleCallback(
                trim((string) $request->query->get('code')),
                trim((string) $request->query->get('state')),
            );

            return $this->settingsRedirect(['gmail' => 'connected']);
        } catch (\Throwable $error) {
            return $this->settingsRedirect(['gmail_error' => $error->getMessage()]);
        }
    }

    #[Route('/sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        return new JsonResponse($this->gmail->sync());
    }

    #[Route('/disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse
    {
        $this->store->clear();

        return new JsonResponse(['connected' => false, 'sendPermission' => false]);
    }

    #[Route('/test-preview/{id}', methods: ['GET'])]
    public function testPreview(Application $application): JsonResponse
    {
        $email = $this->emailFactory->create($application);

        return new JsonResponse([
            'applicationId' => $application->getId(),
            'subject' => $email['subject'],
            'body' => $email['body'],
            'attachmentNames' => $email['attachmentNames'],
        ]);
    }

    #[Route('/test-send', methods: ['POST'])]
    public function testSend(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $recipient = trim((string) ($data['recipient'] ?? ''));
        $applicationId = (int) ($data['applicationId'] ?? 0);

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return new JsonResponse(['error' => 'Adresse e-mail de test invalide.'], 400);
        }

        if ($applicationId <= 0) {
            return new JsonResponse(['error' => 'Sélectionne une candidature préparée.'], 400);
        }

        $application = $this->em->getRepository(Application::class)->find($applicationId);
        if (!$application instanceof Application) {
            return new JsonResponse(['error' => 'Candidature introuvable.'], 404);
        }

        $email = $this->emailFactory->create($application);
        $result = $this->gmail->sendEmail(
            $recipient,
            $email['subject'],
            $email['body'],
            $email['attachments'],
        );

        return new JsonResponse([
            'sent' => true,
            'recipient' => $recipient,
            'gmailMessageId' => $result['id'],
            'subject' => $email['subject'],
            'body' => $email['body'],
            'attachmentNames' => $email['attachmentNames'],
            'applicationStatusChanged' => false,
            'dailyLimitConsumed' => false,
        ], 201);
    }

    #[Route('/messages', methods: ['GET'])]
    public function messages(): JsonResponse
    {
        $items = $this->em->getRepository(InboxMessage::class)->findBy([], ['receivedAt' => 'DESC'], 100);

        return new JsonResponse(array_map(
            static fn (InboxMessage $message): array => $message->toArray(),
            $items,
        ));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function settingsRedirect(array $parameters): RedirectResponse
    {
        $webUrl = $_SERVER['WEB_URL'] ?? $_ENV['WEB_URL'] ?? getenv('WEB_URL') ?: 'http://localhost:3000';

        return new RedirectResponse(rtrim((string) $webUrl, '/').'/parametres?'.http_build_query($parameters));
    }
}
