<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SourceConversionReportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SourceConversionReportController
{
    public function __construct(private SourceConversionReportService $report)
    {
    }

    #[Route('/api/reporting/source-conversion', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->report->report());
    }
}
