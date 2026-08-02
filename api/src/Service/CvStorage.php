<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CvStorage
{
    public function __construct(private string $uploadDir) {}

    public function store(UploadedFile $file): string
    {
        if (!is_dir($this->uploadDir) && !mkdir($concurrentDirectory = $this->uploadDir, 0700, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage.');
        }
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf','doc','docx'], true)) throw new \InvalidArgumentException('Formats acceptés : PDF, DOC, DOCX.');
        if ($file->getSize() > 10 * 1024 * 1024) throw new \InvalidArgumentException('Le fichier dépasse 10 Mo.');
        $stored = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->uploadDir, $stored);
        chmod($this->uploadDir.'/'.$stored, 0600);
        return $stored;
    }

    public function path(string $storedName): string { return $this->uploadDir.'/'.$storedName; }
    public function delete(string $storedName): void { $path = $this->path($storedName); if (is_file($path)) unlink($path); }
}
