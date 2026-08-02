<?php

namespace App\Service;

use App\Entity\CvDocument;
use Doctrine\ORM\EntityManagerInterface;

final class CvSelector
{
    public function __construct(private EntityManagerInterface $em) {}

    public function select(string $language, string $text): ?CvDocument
    {
        $repo = $this->em->getRepository(CvDocument::class);
        $documents = $repo->findBy(['language' => $language, 'active' => true], ['defaultForLanguage' => 'DESC', 'createdAt' => 'DESC']);
        if ($documents === []) return null;

        $text = mb_strtolower($text);
        $best = null;
        $bestScore = -1;
        foreach ($documents as $document) {
            $score = $document->isDefaultForLanguage() ? 1 : 0;
            foreach ($document->getTags() as $tag) {
                if (str_contains($text, mb_strtolower((string) $tag))) $score += 2;
            }
            if ($score > $bestScore) { $best = $document; $bestScore = $score; }
        }
        return $best;
    }
}
