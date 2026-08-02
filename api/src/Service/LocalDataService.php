<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;

final class LocalDataService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function profile(): CandidateProfile
    {
        $profile = $this->em->getRepository(CandidateProfile::class)->findOneBy([]);
        if ($profile === null) { $profile = new CandidateProfile(); $this->em->persist($profile); $this->em->flush(); }
        return $profile;
    }

    public function settings(): UserSettings
    {
        $settings = $this->em->getRepository(UserSettings::class)->findOneBy([]);
        if ($settings === null) { $settings = new UserSettings(); $this->em->persist($settings); $this->em->flush(); }
        return $settings;
    }
}
