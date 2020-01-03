<?php

namespace App\Service;

use App\Entity\Advertisement;
use App\Entity\Classifications\Category;
use App\Entity\Classifications\ClassificationInterface;
use App\Entity\Classifications\Section;
use App\Entity\Classifications\SubCategory;
use App\Entity\Locations\Area;
use App\Entity\Locations\City;
use App\Entity\Locations\Country;
use App\Entity\Locations\LocationInterface;
use App\Entity\Locations\Region;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BreadcrumbsGenerator
{

    private $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Generation of breadcrumbs for location
     * @param LocationInterface $location
     * @return array
     */
    public function getLocationBreadcrumbs(LocationInterface $location): array
    {
        $breadcrumbs = [
            [
                'path' => $this->router->generate('home'),
                'title' => 'Home',
            ],
        ];
        if ($location instanceof Area) {
            $this->appendLocation($location, $breadcrumbs);
        } elseif ($location instanceof Country) {
            $this->appendLocation($location->getArea(), $breadcrumbs);
            $this->appendLocation($location, $breadcrumbs);
        } elseif ($location instanceof City) {
            $this->appendLocation($location->getCountry()->getArea(), $breadcrumbs);
            $this->appendLocation($location->getCountry(), $breadcrumbs);
            $this->appendLocation($location, $breadcrumbs);
        } elseif ($location instanceof Region) {
            $this->appendLocation($location->getCity()->getCountry()->getArea(), $breadcrumbs);
            $this->appendLocation($location->getCity()->getCountry(), $breadcrumbs);
            $this->appendLocation($location->getCity(), $breadcrumbs);
            $this->appendLocation($location, $breadcrumbs);
        }
        return $breadcrumbs;
    }

    /**
     * Generation of breadcrumbs for advertisement
     * @param Advertisement $advertisement
     * @return array
     */
    public function getAdvertisementBreadcrumbs(Advertisement $advertisement): array
    {
        return $this->getClassificationBreadcrumbs($advertisement->getLocation(), $advertisement->getClassification());
    }

    /**
     * Generation of breadcrumbs for location and classification
     * @param LocationInterface $location
     * @param ClassificationInterface $classification
     * @return array
     */
    public function getClassificationBreadcrumbs(LocationInterface $location, ClassificationInterface $classification): array
    {
        $breadcrumbs = $this->getLocationBreadcrumbs($location);
        if ($classification instanceof Section) {
            $breadcrumbs[] = [
                'path' => $this->router->generate('section.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getSlug(),
                ]),
                'title' => $classification->getTitle(),
            ];
        } elseif ($classification instanceof Category) {
            $breadcrumbs[] = [
                'path' => $this->router->generate('section.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getSection()->getSlug(),
                ]),
                'title' => $classification->getSection()->getTitle(),
            ];
            $breadcrumbs[] = [
                'path' => $this->router->generate('category.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getSection()->getSlug(),
                    'category' => $classification->getRealSlug(),
                ]),
                'title' => $classification->getTitle(),
            ];
        } elseif ($classification instanceof SubCategory) {
            $breadcrumbs[] = [
                'path' => $this->router->generate('section.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getCategory()->getSection()->getSlug(),
                ]),
                'title' => $classification->getCategory()->getSection()->getTitle(),
            ];
            $breadcrumbs[] = [
                'path' => $this->router->generate('category.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getCategory()->getSection()->getSlug(),
                    'category' => $classification->getCategory()->getRealSlug(),
                ]),
                'title' => $classification->getCategory()->getTitle(),
            ];
            $breadcrumbs[] = [
                'path' => $this->router->generate('sub_category.location', [
                    'location' => $location->getSlug(),
                    'section' => $classification->getCategory()->getSection()->getSlug(),
                    'category' => $classification->getCategory()->getRealSlug(),
                    'sub_category' => $classification->getRealSlug(),
                ]),
                'title' => $classification->getRealSlug(),
            ];
        }
        return $breadcrumbs;
    }

    /**
     * Generation of breadcrumbs for user
     * @return array
     */
    public function getUserBreadcrumbs(): array
    {
        return [
            [
                'path' => $this->router->generate('home'),
                'title' => 'Home',
            ],
        ];
    }

    /**
     * Add a new location element to the breadcrumb array
     * @param LocationInterface $location
     * @param array $breadcrumbs
     */
    private function appendLocation(LocationInterface $location, array &$breadcrumbs): void
    {
        $breadcrumbs[] = [
            'path' => $this->router->generate('location.location', ['location' => $location->getSlug()]),
            'title' => $location->getTitle(),
        ];
    }
}