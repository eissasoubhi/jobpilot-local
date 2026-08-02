<?php

namespace App\Service;

final class SourceRegistry
{
    public function all(): array
    {
        return [
            ['name'=>'LinkedIn','url'=>'https://linkedin.com/jobs','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Malt','url'=>'https://www.malt.fr','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Free-Work','url'=>'https://www.free-work.com','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Apec','url'=>'https://www.apec.fr','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Collective.work','url'=>'https://www.collective.work','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Crème de la Crème','url'=>'https://cremedelacreme.io','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'FreelanceRepublik','url'=>'https://app.freelancerepublik.com','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Comet','url'=>'https://www.comet.co','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Cherry Pick','url'=>'https://app.cherry-pick.io','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'LeHibou','url'=>'https://www.lehibou.com','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Mindquest','url'=>'https://mindquest.io/fr','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'WeLoveDevs','url'=>'https://welovedevs.com/fr','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Sept Lieues','url'=>'https://www.sept-lieues.com','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Jean-Michel.io','url'=>'https://consultant.jean-michel.io','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Welcome to the Jungle','url'=>'https://www.welcometothejungle.com/fr','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Cadremploi','url'=>'https://www.cadremploi.fr','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'HelloWork','url'=>'https://www.hellowork.com','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Jobijoba','url'=>'https://www.jobijoba.com','category'=>'AGGREGATOR','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'EURES','url'=>'https://europa.eu/eures','category'=>'AGGREGATOR','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Freelance-Informatique','url'=>'https://www.freelance-informatique.fr','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Indeed','url'=>'https://fr.indeed.com','category'=>'AGGREGATOR','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Adzuna','url'=>'https://www.adzuna.fr','category'=>'AGGREGATOR','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Kicklox','url'=>'https://app.kicklox.com','category'=>'FREELANCE_SPECIALIST','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Talent.com','url'=>'https://fr.talent.com','category'=>'AGGREGATOR','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'SmartRecruiters','url'=>'https://jobs.smartrecruiters.com','category'=>'ATS','mode'=>'EXTENSION_ONE_CLICK'],
            ['name'=>'GetYourJob','url'=>'https://getyourjob.pro','category'=>'AGGREGATOR','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Le Studio Tech','url'=>'https://app.lestudiotech.com','category'=>'FREELANCE_SPECIALIST','mode'=>'MANUAL_EXTENSION'],
            ['name'=>'Meteojob','url'=>'https://www.meteojob.com','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'Michael Page','url'=>'https://www.michaelpage.fr','category'=>'SALARIED_AND_GENERAL','mode'=>'ALERTS_EXTENSION'],
            ['name'=>'France Travail','url'=>'https://www.francetravail.fr','category'=>'SALARIED_AND_GENERAL','mode'=>'OFFICIAL_API_PLANNED'],
        ];
    }
}
