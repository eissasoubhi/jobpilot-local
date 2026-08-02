<?php
namespace App\Controller;

use App\Entity\InboxMessage;
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
    public function __construct(private GmailService $gmail, private GmailTokenStore $store, private EntityManagerInterface $em) {}

    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse { return new JsonResponse(['connected'=>$this->store->isConnected()]); }

    #[Route('/start', methods: ['GET'])]
    public function start(): RedirectResponse { return new RedirectResponse($this->gmail->authorizationUrl()); }

    #[Route('/callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $this->gmail->handleCallback((string) $request->query->get('code'), (string) $request->query->get('state'));
        return new RedirectResponse((string) ($_ENV['WEB_URL'] ?? 'http://localhost:3000').'/parametres?gmail=connected');
    }

    #[Route('/sync', methods: ['POST'])]
    public function sync(): JsonResponse { return new JsonResponse($this->gmail->sync()); }

    #[Route('/disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse { $this->store->clear(); return new JsonResponse(['connected'=>false]); }

    #[Route('/messages', methods: ['GET'])]
    public function messages(): JsonResponse
    {
        $items = $this->em->getRepository(InboxMessage::class)->findBy([], ['receivedAt'=>'DESC'], 100);
        return new JsonResponse(array_map(static fn(InboxMessage $m) => $m->toArray(), $items));
    }
}
