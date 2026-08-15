<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Messaging\Application\InboxMessageUrgencyEvaluator;
use App\Service\ApplicationEmailFactory;
use App\Service\GmailService;
use App\Service\GmailTokenStore;
use App\Service\JobSearchSyncService;
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
        private JobSearchSyncService $jobSync,
        private EntityManagerInterface $em,
        private InboxMessageUrgencyEvaluator $urgencyEvaluator,
    ) {}

    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $connected = $this->store->isConnected();
        $readPermission = $connected && $this->gmail->hasReadPermission();
        $sendPermission = $connected && $this->gmail->hasSendPermission();

        return new JsonResponse([
            'connected' => $connected,
            'readPermission' => $readPermission,
            'readPermissionMessage' => match (true) {
                !$connected => 'Gmail n’est pas connecté.',
                !$readPermission => 'Le droit de lecture Gmail n’est pas détecté. Déconnecte puis reconnecte Gmail en acceptant gmail.readonly.',
                default => null,
            },
            'sendPermission' => $sendPermission,
            'sendPermissionMessage' => match (true) {
                !$connected => 'Gmail n’est pas connecté.',
                !$sendPermission => 'Le droit d’envoi Gmail n’est pas détecté. Déconnecte puis reconnecte Gmail en acceptant gmail.send.',
                default => null,
            },
            'lastSync' => $this->gmail->lastSyncSummary(),
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
        if (!$this->store->isConnected()) {
            return new JsonResponse(['error' => 'Gmail n’est pas connecté.'], 409);
        }
        if (!$this->gmail->hasReadPermission()) {
            return new JsonResponse([
                'error' => 'Le droit gmail.readonly manque. Déconnecte puis reconnecte Gmail.',
            ], 409);
        }

        try {
            $result = $this->jobSync->sync(true, 'gmail', 'manual');
        } catch (\Throwable $error) {
            return new JsonResponse(['error' => $error->getMessage()], 502);
        }

        return new JsonResponse([
            ...$this->gmail->lastSyncSummary(),
            'offersImported' => (int) ($result['imported'] ?? 0),
            'offerDuplicates' => (int) ($result['duplicates'] ?? 0),
            'message' => (string) ($result['message'] ?? 'Synchronisation Gmail terminée.'),
            'skipped' => (bool) ($result['skipped'] ?? false),
        ]);
    }

    #[Route('/disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse
    {
        $this->store->clear();

        return new JsonResponse([
            'connected' => false,
            'readPermission' => false,
            'readPermissionMessage' => 'Gmail n’est pas connecté.',
            'sendPermission' => false,
            'sendPermissionMessage' => 'Gmail n’est pas connecté.',
        ]);
    }

    #[Route('/test-preview/{id}', methods: ['GET'])]
    public function testPreview(Application $application): JsonResponse
    {
        try {
            $email = $this->emailFactory->create($application);
        } catch (\Throwable $error) {
            return new JsonResponse(['error' => $error->getMessage()], 422);
        }

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
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'La requête d’envoi de test est invalide.'], 400);
        }

        $recipient = trim((string) ($data['recipient'] ?? ''));
        $applicationId = (int) ($data['applicationId'] ?? 0);

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return new JsonResponse(['error' => 'Adresse e-mail de test invalide.'], 400);
        }
        if ($applicationId <= 0) {
            return new JsonResponse(['error' => 'Sélectionne une candidature préparée.'], 400);
        }
        if (!$this->store->isConnected()) {
            return new JsonResponse(['error' => 'Gmail n’est pas connecté. Connecte Gmail avant de lancer le test.'], 409);
        }
        if (!$this->gmail->hasSendPermission()) {
            return new JsonResponse([
                'error' => 'Le droit gmail.send manque. Déconnecte puis reconnecte Gmail en acceptant l’autorisation d’envoi.',
            ], 409);
        }

        $application = $this->em->getRepository(Application::class)->find($applicationId);
        if (!$application instanceof Application) {
            return new JsonResponse(['error' => 'Candidature introuvable.'], 404);
        }

        try {
            $email = $this->emailFactory->create($application);
        } catch (\Throwable $error) {
            return new JsonResponse(['error' => $error->getMessage()], 422);
        }

        try {
            $result = $this->gmail->sendEmail(
                $recipient,
                $email['subject'],
                $email['body'],
                $email['attachments'],
            );
        } catch (\Throwable $error) {
            return new JsonResponse(['error' => $error->getMessage()], 502);
        }

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
    public function messages(Request $request): JsonResponse
    {
        $criteria = [];
        $category = trim((string) $request->query->get('category'));
        if ($category !== '' && $category !== 'ALL') {
            $criteria['category'] = $category;
        }
        if ($request->query->has('actionRequired')) {
            $criteria['actionRequired'] = filter_var(
                $request->query->get('actionRequired'),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
        }
        if ($request->query->has('processed')) {
            $criteria['processed'] = filter_var(
                $request->query->get('processed'),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
        }
        $limit = max(1, min(250, (int) $request->query->get('limit', 100)));
        $items = $this->em->getRepository(InboxMessage::class)->findBy($criteria, ['receivedAt' => 'DESC'], $limit);

        return new JsonResponse(array_map(
            fn (InboxMessage $message): array => $this->messagePayload($message),
            $items,
        ));
    }

    #[Route('/messages/{id}/processed', methods: ['PATCH'])]
    public function markProcessed(InboxMessage $message, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = [];
        }
        $processed = array_key_exists('processed', $data) ? (bool) $data['processed'] : true;
        $message->markProcessed($processed);
        $this->em->flush();

        return new JsonResponse($this->messagePayload($message));
    }

    /** @return array<string, mixed> */
    private function messagePayload(InboxMessage $message): array
    {
        return [
            ...$message->toArray(),
            'urgency' => $this->urgencyEvaluator->evaluate($message),
        ];
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
