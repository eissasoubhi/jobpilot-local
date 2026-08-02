<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CvDocument;
use App\Service\CvStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cvs')]
final class CvController
{
    public function __construct(private EntityManagerInterface $em, private CvStorage $storage) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(CvDocument::class)->findBy([], ['createdAt' => 'DESC']);

        return new JsonResponse(array_map(static fn (CvDocument $cv): array => $cv->toArray(), $items));
    }

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if ($file === null) {
            throw new \InvalidArgumentException('Le fichier CV est obligatoire.');
        }

        $language = strtolower((string) $request->request->get('language', 'fr'));
        if (!in_array($language, ['fr', 'en'], true)) {
            throw new \InvalidArgumentException('Langue CV invalide.');
        }

        $name = trim((string) $request->request->get('name', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du CV est obligatoire.');
        }

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize() ?: 0;
        $stored = $this->storage->store($file);

        $defaultForLanguage = filter_var(
            $request->request->get('defaultForLanguage', false),
            FILTER_VALIDATE_BOOL,
        );

        if ($defaultForLanguage) {
            foreach ($this->em->getRepository(CvDocument::class)->findBy([
                'language' => $language,
                'defaultForLanguage' => true,
            ]) as $existingDefault) {
                $existingDefault->configure(['defaultForLanguage' => false]);
            }
        }

        $cv = new CvDocument($name, $originalName, $stored, $language, $mimeType, $size);
        $tags = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->request->get('tags', '')),
        )));
        $cv->configure([
            'category' => (string) $request->request->get('category', 'Général'),
            'tags' => $tags,
            'defaultForLanguage' => $defaultForLanguage,
        ]);

        $this->em->persist($cv);
        $this->em->flush();

        return new JsonResponse($cv->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(CvDocument $cv, Request $request): JsonResponse
    {
        $cv->configure($request->toArray());
        $this->em->flush();

        return new JsonResponse($cv->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CvDocument $cv): JsonResponse
    {
        $this->storage->delete($cv->getStoredName());
        $this->em->remove($cv);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    #[Route('/{id}/download', methods: ['GET'])]
    public function download(CvDocument $cv): BinaryFileResponse
    {
        $path = $this->storage->path($cv->getStoredName());
        if (!is_file($path)) {
            throw new NotFoundHttpException('Fichier CV introuvable.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $cv->toArray()['originalName'],
        );

        return $response;
    }
}
