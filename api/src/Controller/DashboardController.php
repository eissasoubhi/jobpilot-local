<?php
namespace App\Controller;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Entity\JobOffer;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/api/dashboard', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $jobs = $this->em->getRepository(JobOffer::class);
        $apps = $this->em->getRepository(Application::class);
        $positions = $this->em->getRepository(Positioning::class);
        $messages = $this->em->getRepository(InboxMessage::class);
        $recent = $jobs->findBy([], ['discoveredAt'=>'DESC'], 5);
        return new JsonResponse([
            'counts' => [
                'jobs' => $jobs->count([]),
                'prepared' => $apps->count(['status'=>'READY_TO_SUBMIT']),
                'submitted' => $apps->count(['status'=>'SUBMITTED']),
                'positionings' => $positions->count([]),
                'messages' => $messages->count([]),
            ],
            'recentJobs' => array_map(static fn(JobOffer $j) => $j->toArray(), $recent),
        ]);
    }
}
