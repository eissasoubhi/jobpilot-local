<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\InboxMessage;
use App\Entity\JobTimelineEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
final class GmailInboxTimelineListener
{
    public function __construct(private JobTimelineRecorder $timeline)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof InboxMessage) {
                continue;
            }

            $application = $entity->getApplication();
            if ($application === null) {
                continue;
            }

            $mapping = $this->mapping($entity->getCategory());
            if ($mapping === null) {
                continue;
            }

            $statusChange = $unitOfWork->getEntityChangeSet($application)['status'] ?? null;
            if (!is_array($statusChange) || count($statusChange) !== 2) {
                continue;
            }

            [$previousStatus, $nextStatus] = array_values($statusChange);
            if ($previousStatus === $nextStatus || $nextStatus !== $mapping['status']) {
                continue;
            }

            $event = $this->timeline->record(
                $application->getJobOffer(),
                $mapping['eventType'],
                [
                    'category' => $entity->getCategory(),
                    'previousStatus' => (string) $previousStatus,
                ],
                $application,
                $entity->getReceivedAt(),
                'gmail-inbox',
            );

            // onFlush runs after Doctrine computed the initial change sets. New timeline
            // entities therefore need their change set computed explicitly so they are
            // inserted in the same transaction as the InboxMessage and status update.
            $unitOfWork->computeChangeSet(
                $em->getClassMetadata(JobTimelineEvent::class),
                $event,
            );
        }
    }

    /** @return array{status: string, eventType: string}|null */
    private function mapping(string $category): ?array
    {
        return match ($category) {
            'APPLICATION_REPLY' => [
                'status' => 'RESPONSE_RECEIVED',
                'eventType' => JobTimelineEventType::RESPONSE_RECEIVED,
            ],
            'INFORMATION_REQUEST' => [
                'status' => 'INFORMATION_REQUESTED',
                'eventType' => JobTimelineEventType::RESPONSE_RECEIVED,
            ],
            'REJECTION' => [
                'status' => 'REJECTED',
                'eventType' => JobTimelineEventType::REJECTED,
            ],
            'INTERVIEW_REQUEST' => [
                'status' => 'INTERVIEW',
                'eventType' => JobTimelineEventType::INTERVIEW,
            ],
            default => null,
        };
    }
}
