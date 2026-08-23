<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;

final class ReviewDecisionUndoService
{
    /**
     * Restores the local state that existed immediately before a Review Queue
     * decision. This never attempts to undo an external submission.
     */
    public function undo(Application $application): string
    {
        $previousStatus = $application->getStatus();
        if (!in_array($previousStatus, ['IGNORED_NOT_MATCH', 'OFFER_UNAVAILABLE', 'SUBMITTED'], true)) {
            throw new \LogicException('La dernière décision de cette candidature ne peut pas être annulée.');
        }

        if ($previousStatus === 'SUBMITTED' && $application->getGmailMessageId() !== null) {
            throw new \LogicException(
                'Cette candidature a été réellement envoyée par Gmail. JobPilot ne peut pas annuler un envoi externe.',
            );
        }

        if ($previousStatus === 'OFFER_UNAVAILABLE') {
            $application->getJobOffer()->fill(['status' => 'PREPARED']);
        }

        $application->fill([
            'status' => 'READY_TO_SUBMIT',
            'submittedAt' => null,
            'channel' => 'Préparation locale',
        ]);

        return $previousStatus;
    }
}
