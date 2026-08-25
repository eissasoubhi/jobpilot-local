<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final readonly class ApiPagination
{
    private const int DEFAULT_LIMIT = 25;
    private const int MAX_LIMIT = 100;

    private function __construct(
        public int $page,
        public int $limit,
    ) {}

    public static function fromRequest(Request $request): ?self
    {
        if (!$request->query->has('page') && !$request->query->has('limit')) {
            return null;
        }

        return new self(
            max(1, (int) $request->query->get('page', 1)),
            max(1, min(self::MAX_LIMIT, (int) $request->query->get('limit', self::DEFAULT_LIMIT))),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /** @return array{page:int,limit:int,total:int,totalPages:int} */
    public function metadata(int $total): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'total' => $total,
            'totalPages' => $total === 0 ? 0 : (int) ceil($total / $this->limit),
        ];
    }
}
