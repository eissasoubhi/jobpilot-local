<?php

namespace App\Service;

final class LanguageDetector
{
    private const FR = ['développeur','mission','poste','expérience','compétences','entreprise','candidat','télétravail','salaire','contrat','équipe','vous','nous','recherche'];
    private const EN = ['developer','engineer','experience','skills','company','candidate','remote','salary','contract','team','you','we','looking'];

    public function detect(string $text): string
    {
        $text = mb_strtolower($text);
        $fr = $this->count($text, self::FR);
        $en = $this->count($text, self::EN);
        return $en > $fr ? 'en' : 'fr';
    }

    private function count(string $text, array $words): int
    {
        return array_sum(array_map(static fn(string $word): int => substr_count($text, $word), $words));
    }
}
