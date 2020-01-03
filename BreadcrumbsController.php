<?php

namespace App\Controller;

use App\Entity\Advertisement;
use App\Exception\ClassificationNotFoundException;
use App\Exception\LocationNotAvailableException;
use App\Exception\LocationNotFoundException;
use App\Exception\NotFoundApiException;
use App\Helper\Domain;
use App\Repository\AdvertisementRepository;
use App\Response\ApiResponse;
use App\Service\BreadcrumbsGenerator;
use App\Service\ClassificationService;
use App\Service\LocationService;
use JMS\Serializer\SerializerInterface;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BreadcrumbsController extends AbstractController
{

    /**
     * Get breadcrumbs.
     * @SWG\Parameter(
     *     name="location",
     *     in="path",
     *     type="string",
     *     default="united-states"
     * )
     * @SWG\Parameter(
     *     name="classification",
     *     in="path",
     *     type="string",
     *     default="buy-and-sell"
     * )
     * @SWG\Parameter(
     *     name="advertisement",
     *     in="path",
     *     type="string",
     *     default="advertisement-25_25"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the breadcrumbs list."
     * )
     * @SWG\Tag(name="breadcrumbs")
     * @param Domain $domain
     * @param SerializerInterface $serializer
     * @param BreadcrumbsGenerator $breadcrumbsGenerator
     * @param LocationService $locationService
     * @param ClassificationService $classificationService
     * @param AdvertisementRepository $advertisementRepository
     * @param string|null $advertisement
     * @param string|null $location
     * @param string|null $classification
     * @return ApiResponse
     * @throws NotFoundApiException
     */
    public function apiBreadcrumbsAction(
        Domain $domain,
        SerializerInterface $serializer,
        BreadcrumbsGenerator $breadcrumbsGenerator,
        LocationService $locationService,
        ClassificationService $classificationService,
        AdvertisementRepository $advertisementRepository,
        string $advertisement = null,
        string $location = null,
        string $classification = null
    )
    {
        if ($advertisement) {
            /* @var $advertisementInst Advertisement */
            $advertisementInst = $advertisementRepository->findOneBy(['slug' => $advertisement]);
            if (!$advertisementInst) {
                throw new NotFoundApiException('Incorrect slug for advertisement.');
            }
            $breadcrumbs = $breadcrumbsGenerator->getAdvertisementBreadcrumbs($advertisementInst);
        } elseif ($location && $classification) {
            try {
                $locationInst = $locationService->getInstanceBySlugAndDomain($location, $domain);
                $classificationInst = $classificationService->slugToInstance($classification);
            } catch (ClassificationNotFoundException | LocationNotFoundException| LocationNotAvailableException $ex) {
                throw new NotFoundApiException($ex->getMessage());
            }
            $breadcrumbs = $breadcrumbsGenerator->getClassificationBreadcrumbs($locationInst, $classificationInst);
        } elseif ($location) {
            try {
                $locationInst = $locationService->getInstanceBySlugAndDomain($location, $domain);
            } catch (LocationNotFoundException| LocationNotAvailableException $ex) {
                throw new NotFoundApiException($ex->getMessage());
            }
            $breadcrumbs = $breadcrumbsGenerator->getLocationBreadcrumbs($locationInst);
        } else {
            throw new NotFoundApiException();
        }
        return (new ApiResponse($serializer->serialize(
            $breadcrumbs,
            'json'
        )))->setSharedMaxAge(86400);
    }
}