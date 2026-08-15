<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\InboxMessage;
use App\Messaging\Application\InboxMessageUrgencyEvaluator;
use PHPUnit\Framework\TestCase;

final class InboxMessageUrgencyEvaluatorTest extends TestCase
{
    private InboxMessageUrgencyEvaluator $evaluator;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->evaluator = new InboxMessageUrgencyEvaluator();
        $this->now = new \DateTimeImmutable('2026-08-14T12:00:00+02:00');
    }

    public function testInterviewAndInformationRequestsAreUrgentAndExplainWhy(): void
    {
        $interview = $this->message(
            'INTERVIEW_REQUEST',
            true,
            'Invitation entretien technique',
            'Choisissez un créneau.',
            '-2 hours',
        );
        $information = $this->message(
            'INFORMATION_REQUEST',
            true,
            'Informations complémentaires',
            'Pouvez-vous nous transmettre votre disponibilité ?',
            '-3 hours',
        );

        $interviewUrgency = $this->evaluator->evaluate($interview, $this->now);
        $informationUrgency = $this->evaluator->evaluate($information, $this->now);

        self::assertSame('URGENT', $interviewUrgency['level']);
        self::assertTrue($interviewUrgency['actionRequired']);
        self::assertContains('Entretien ou rendez-vous à organiser.', $interviewUrgency['reasons']);
        self::assertSame('Planifier ou répondre à l’entretien', $interviewUrgency['recommendedAction']);

        self::assertSame('URGENT', $informationUrgency['level']);
        self::assertContains('Le recruteur attend des informations ou une réponse.', $informationUrgency['reasons']);
    }

    public function testDirectRecruiterOpportunityIsPriorityUntilTimingOrAgeMakesItUrgent(): void
    {
        $recent = $this->message(
            'RECRUITER_OPPORTUNITY',
            true,
            'Mission Symfony',
            'Votre profil nous intéresse pour une mission.',
            '-2 hours',
        );
        $explicitDeadline = $this->message(
            'RECRUITER_OPPORTUNITY',
            true,
            'Mission Symfony urgente',
            'Pouvez-vous répondre aujourd’hui ?',
            '-2 hours',
        );
        $old = $this->message(
            'RECRUITER_OPPORTUNITY',
            true,
            'Mission Symfony',
            'Votre profil nous intéresse pour une mission.',
            '-50 hours',
        );

        self::assertSame('PRIORITY', $this->evaluator->evaluate($recent, $this->now)['level']);

        $deadlineUrgency = $this->evaluator->evaluate($explicitDeadline, $this->now);
        self::assertSame('URGENT', $deadlineUrgency['level']);
        self::assertContains('Un délai ou un signal d’urgence explicite a été détecté.', $deadlineUrgency['reasons']);

        $oldUrgency = $this->evaluator->evaluate($old, $this->now);
        self::assertSame('URGENT', $oldUrgency['level']);
        self::assertContains('Ce message attend une action depuis plus de 48 h.', $oldUrgency['reasons']);
    }

    public function testRejectionsAlertsAndConfirmationsNeverBecomeUrgentFromTimingWordsAlone(): void
    {
        foreach (['REJECTION', 'JOB_ALERT', 'APPLICATION_CONFIRMATION'] as $category) {
            $message = $this->message(
                $category,
                false,
                'URGENT - réponse aujourd’hui',
                'Merci de répondre ASAP.',
                '-72 hours',
            );

            $urgency = $this->evaluator->evaluate($message, $this->now);
            self::assertSame('NORMAL', $urgency['level'], $category);
            self::assertFalse($urgency['actionRequired'], $category);
            self::assertSame([], $urgency['reasons'], $category);
        }
    }

    public function testProcessedMessageStopsBeingUrgentWithoutChangingItsCategory(): void
    {
        $message = $this->message(
            'INTERVIEW_REQUEST',
            true,
            'Entretien urgent',
            'Merci de répondre aujourd’hui.',
            '-30 hours',
        );
        $message->markProcessed();

        $urgency = $this->evaluator->evaluate($message, $this->now);

        self::assertSame('INTERVIEW_REQUEST', $message->getCategory());
        self::assertSame('NORMAL', $urgency['level']);
        self::assertFalse($urgency['actionRequired']);
        self::assertSame([], $urgency['reasons']);
        self::assertNull($urgency['recommendedAction']);
    }

    private function message(
        string $category,
        bool $actionRequired,
        string $subject,
        string $snippet,
        string $receivedModifier,
    ): InboxMessage {
        return (new InboxMessage('gmail-'.bin2hex(random_bytes(5)), 'thread'))
            ->fill(
                'recruiter@example.com',
                $subject,
                $snippet,
                $this->now->modify($receivedModifier),
                $category,
                actionRequired: $actionRequired,
            );
    }
}
