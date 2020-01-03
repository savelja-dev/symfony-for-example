<?php

namespace App\Service;

use App\Entity\Advertisement;
use App\Entity\Classification;
use App\Entity\Classifications\ClassificationInterface;
use App\Entity\Location;
use App\Entity\Locations\LocationInterface;
use App\Helper\Domain;
use App\Repository\AdvertisementRepository;
use App\Repository\ClassificationRepository;
use App\Repository\LocationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class SitemapService
{
    /**
     * @var LocationRepository
     */
    private $locationRepository;
    /**
     * @var AdvertisementRepository
     */
    private $advertisementRepository;
    /**
     * @var ClassificationRepository
     */
    private $classificationRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->locationRepository = $entityManager->getRepository(Location::class);
        $this->classificationRepository = $entityManager->getRepository(Classification::class);
        $this->advertisementRepository = $entityManager->getRepository(Advertisement::class);
    }

    /**
     * We take all the classifications and locations where the ad is created.
     * Next, we extract the dates when the site build ads were created.
     * @param Domain $domain
     * @return \Generator
     */
    public function getLocationClassificationNotEmpty(Domain $domain)
    {
        $areas = $this->locationRepository->findAreasByDomain($domain);
        $sections = $this->classificationRepository->findSectionByParams($domain);
        foreach ($areas as $area) {
            foreach ($sections as $section) {
                if (!($dates = $this->advertisementRepository->getActualDates($area, $section))) {
                    continue;
                }
                yield [
                    'area' => $area,
                    'section' => $section,
                    'dates' => $dates
                ];
            }
        }
        yield null;
    }

    /**
     * We get all the ads by parameters to generate the lowest-level sitemap.
     * @param LocationInterface $location
     * @param ClassificationInterface $classification
     * @param DateTime $dateTime
     * @return \Generator
     */
    public function getAdvertisementsByLocationAndClassificationAndDate(
        LocationInterface $location,
        ClassificationInterface $classification,
        DateTime $dateTime
    )
    {
        foreach ($this->advertisementRepository->findByLocationAndClassification($location, $classification, $dateTime) as $adv) {
            yield $adv;
        }
        yield null;
    }
}