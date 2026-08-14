<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Application
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private JobOffer $jobOffer;
    #[ORM\Column(length: 100)] private string $channel = 'Préparation locale';
    #[ORM\Column(length: 50)] private string $status = 'DRAFT';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $submittedAt = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?CvDocument $cvDocument = null;
    #[ORM\Column(type: 'text')] private string $message = '';
    #[ORM\Column(type: 'text')] private string $coverLetter = '';
    #[ORM\Column(type: 'text')] private string $generatedCoverLetter = '';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $coverLetterEditedAt = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $compensationAnswer = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $confirmationRef = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $gmailMessageId = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $submissionError = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $submissionAttemptedAt = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct(JobOffer $jobOffer)
    {
        $this->jobOffer = $jobOffer;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getJobOffer(): JobOffer { return $this->jobOffer; }
    public function getStatus(): string { return $this->status; }
    public function getCvDocument(): ?CvDocument { return $this->cvDocument; }
    public function getMessage(): string { return $this->message; }
    public function getCoverLetter(): string { return $this->coverLetter; }
    public function getGeneratedCoverLetter(): string { return $this->generatedCoverLetter; }
    public function getCoverLetterEditedAt(): ?\DateTimeImmutable { return $this->coverLetterEditedAt; }
    public function isCoverLetterManuallyEdited(): bool { return $this->coverLetterEditedAt !== null; }
    public function getCompensationAnswer(): ?string { return $this->compensationAnswer; }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function getGmailMessageId(): ?string { return $this->gmailMessageId; }
    public function getSubmissionError(): ?string { return $this->submissionError; }
    public function getSubmissionAttemptedAt(): ?\DateTimeImmutable { return $this->submissionAttemptedAt; }

    public function prepare(?CvDocument $cv, string $message, string $coverLetter, ?string $compensation): self
    {
        if (in_array($this->status, ['SUBMITTED', 'SUBMISSION_PENDING'], true)) {
            return $this;
        }

        $this->cvDocument = $cv;
        $this->message = $message;
        $this->generatedCoverLetter = $coverLetter;
        if (!$this->isCoverLetterManuallyEdited()) {
            $this->coverLetter = $coverLetter;
        }
        $this->compensationAnswer = $compensation;
        $this->status = $cv === null ? 'MISSING_CV' : 'READY_TO_SUBMIT';
        $this->submissionError = null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function regenerateMessage(string $message): void
    {
        $this->assertRegenerationAllowed();
        $message = $this->normalizeGeneratedContent($message, 'Le message de motivation');
        $this->message = $message;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function regenerateCoverLetter(string $coverLetter): void
    {
        $this->assertRegenerationAllowed();
        $coverLetter = $this->normalizeGeneratedContent($coverLetter, 'La lettre de motivation');
        $this->generatedCoverLetter = $coverLetter;
        $this->coverLetter = $coverLetter;
        $this->coverLetterEditedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function editCoverLetter(string $coverLetter): void
    {
        $coverLetter = trim(str_replace("\r\n", "\n", $coverLetter));
        if ($coverLetter === '') {
            throw new \InvalidArgumentException('La lettre de motivation ne peut pas être vide.');
        }
        if (mb_strlen($coverLetter) > 50_000) {
            throw new \InvalidArgumentException('La lettre de motivation dépasse la taille maximale autorisée.');
        }

        $this->coverLetter = $coverLetter;
        $this->coverLetterEditedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function resetCoverLetter(): void
    {
        if (trim($this->generatedCoverLetter) === '') {
            throw new \LogicException('Aucune version générée de la lettre de motivation n’est disponible.');
        }

        $this->coverLetter = $this->generatedCoverLetter;
        $this->coverLetterEditedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function attachCv(CvDocument $cv): void
    {
        if (in_array($this->status, ['SUBMITTED', 'SUBMISSION_PENDING'], true)) {
            return;
        }

        $this->cvDocument = $cv;
        $this->status = 'READY_TO_SUBMIT';
        $this->submissionError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markMissingCv(): bool
    {
        if ($this->cvDocument !== null || in_array($this->status, ['SUBMITTED', 'SUBMISSION_PENDING'], true)) {
            return false;
        }

        if ($this->status === 'MISSING_CV') {
            return false;
        }

        $this->status = 'MISSING_CV';
        $this->updatedAt = new \DateTimeImmutable();

        return true;
    }

    public function markSubmissionAttempt(): void
    {
        if ($this->status !== 'READY_TO_SUBMIT') {
            throw new \LogicException('La candidature n’est pas prête pour un envoi automatique.');
        }

        $this->status = 'SUBMISSION_PENDING';
        $this->submissionAttemptedAt = new \DateTimeImmutable();
        $this->submissionError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markSubmittedAutomatically(string $gmailMessageId): void
    {
        $gmailMessageId = trim($gmailMessageId);
        if ($gmailMessageId === '') {
            throw new \InvalidArgumentException('Identifiant Gmail manquant.');
        }

        $this->channel = 'Gmail automatique';
        $this->status = 'SUBMITTED';
        $this->submittedAt = new \DateTimeImmutable();
        $this->gmailMessageId = $gmailMessageId;
        $this->confirmationRef = 'gmail:'.$gmailMessageId;
        $this->submissionError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markSubmissionFailed(string $message): void
    {
        $this->status = 'SUBMISSION_FAILED';
        $this->submissionError = mb_substr(trim($message), 0, 4000);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function applyInboxCategory(string $category): bool
    {
        $nextStatus = match ($category) {
            'INTERVIEW_REQUEST' => 'INTERVIEW',
            'REJECTION' => 'REJECTED',
            'INFORMATION_REQUEST' => 'INFORMATION_REQUESTED',
            'APPLICATION_REPLY' => 'RESPONSE_RECEIVED',
            'APPLICATION_CONFIRMATION' => 'APPLICATION_CONFIRMED',
            default => null,
        };

        if ($nextStatus === null || $this->status === $nextStatus) {
            return false;
        }

        if ($category === 'APPLICATION_CONFIRMATION' && in_array($this->status, ['INTERVIEW', 'REJECTED'], true)) {
            return false;
        }

        $this->status = $nextStatus;
        if ($this->submittedAt === null && in_array($category, ['APPLICATION_CONFIRMATION', 'APPLICATION_REPLY', 'INTERVIEW_REQUEST', 'REJECTION'], true)) {
            $this->submittedAt = new \DateTimeImmutable();
        }
        $this->submissionError = null;
        $this->updatedAt = new \DateTimeImmutable();

        return true;
    }

    public function fill(array $data): self
    {
        foreach (['channel', 'status', 'message', 'coverLetter'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = (string) ($data[$field] ?? '');
            }
        }

        foreach (['compensationAnswer', 'confirmationRef'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $this->{$field} = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('submittedAt', $data)) {
            $this->submittedAt = $this->parseDate($data['submittedAt']);
        }

        if ($this->status === 'SUBMITTED' && $this->submittedAt === null) {
            $this->submittedAt = new \DateTimeImmutable();
        }

        if ($this->status === 'SUBMITTED') {
            $this->submissionError = null;
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'jobOffer' => $this->jobOffer->toArray(),
            'channel' => $this->channel,
            'status' => $this->status,
            'submittedAt' => $this->submittedAt?->format(DATE_ATOM),
            'cvDocument' => $this->cvDocument?->toArray(),
            'message' => $this->message,
            'coverLetter' => $this->coverLetter,
            'coverLetterManuallyEdited' => $this->isCoverLetterManuallyEdited(),
            'coverLetterEditedAt' => $this->coverLetterEditedAt?->format(DATE_ATOM),
            'compensationAnswer' => $this->compensationAnswer,
            'confirmationRef' => $this->confirmationRef,
            'gmailMessageId' => $this->gmailMessageId,
            'submissionError' => $this->submissionError,
            'submissionAttemptedAt' => $this->submissionAttemptedAt?->format(DATE_ATOM),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function assertRegenerationAllowed(): void
    {
        if (in_array($this->status, ['SUBMITTED', 'SUBMISSION_PENDING'], true)) {
            throw new \LogicException('Le contenu d’une candidature déjà envoyée ou en cours d’envoi ne peut pas être régénéré.');
        }
    }

    private function normalizeGeneratedContent(string $content, string $label): string
    {
        $content = trim(str_replace("\r\n", "\n", $content));
        if ($content === '') {
            throw new \InvalidArgumentException($label.' ne peut pas être vide.');
        }
        if (mb_strlen($content) > 50_000) {
            throw new \InvalidArgumentException($label.' dépasse la taille maximale autorisée.');
        }

        return $content;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Date de soumission invalide.');
        }
    }
}
