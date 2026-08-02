<?php

namespace App\Command;

use App\Entity\CandidateProfile;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:bootstrap', description: 'Importe les données initiales au premier lancement.')]
final class BootstrapCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private string $initialDataDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->em->getRepository(CandidateProfile::class)->count([]) === 0) {
            $profile = new CandidateProfile();
            $profile->fill($this->read('profile.json'));
            $this->em->persist($profile);
            $output->writeln('<info>Profil initial importé.</info>');
        }
        if ($this->em->getRepository(UserSettings::class)->count([]) === 0) {
            $settings = new UserSettings();
            $settings->fill($this->read('settings.json'));
            $this->em->persist($settings);
            $output->writeln('<info>Paramètres initiaux importés.</info>');
        }
        $this->em->flush();
        return Command::SUCCESS;
    }

    private function read(string $file): array
    {
        $path = rtrim($this->initialDataDir, '/').'/'.$file;
        if (!is_file($path)) return [];
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
