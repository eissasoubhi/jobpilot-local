<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber
{
    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof UniqueConstraintViolationException => 409,
            $exception instanceof \InvalidArgumentException,
            $exception instanceof \JsonException => 422,
            default => 500,
        };

        $message = $status >= 500
            ? 'Une erreur interne est survenue.'
            : $exception->getMessage();

        $event->setResponse(new JsonResponse(['error' => $message], $status));
    }
}
