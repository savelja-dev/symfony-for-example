<?php

namespace App\Controller;

use App\Exception\NotFoundApiException;
use App\Helper\Domain;
use App\Service\CacheService;
use App\Service\ClassificationService;
use App\Service\LocationService;
use App\Service\SitemapService;
use App\Utils\CacheUtil;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends AbstractController
{

    /**
     * Root sitemap.
     * @param SitemapService $sitemapService
     * @param Domain $domain
     * @param CacheService $cacheService
     * @return Response
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function rootAction(SitemapService $sitemapService, Domain $domain, CacheService $cacheService)
    {
        $tagAwareAdapter = $cacheService->getTagAwareAdapterInstanceForFileSystem();
        $rootSitemapCache = $tagAwareAdapter->getItem(CacheUtil::getKeySitemapRootDomain($domain));
        if (!$rootSitemapCache->isHit()) {
            $rootSitemapXml = $this->render(
                'sitemap/root.xml.twig',
                [
                    'parameters' => $sitemapService->getLocationClassificationNotEmpty($domain)
                ]
            )->getContent();
            $rootSitemapCache->set($rootSitemapXml)->tag(CacheUtil::getTagAdvertisements());
            $tagAwareAdapter->save($rootSitemapCache);
        } else {
            $rootSitemapXml = $rootSitemapCache->get();
        }
        return new Response($rootSitemapXml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Advertisement sitemap.
     * @param string $location
     * @param string $classification
     * @param string $date
     * @param LocationService $locationService
     * @param ClassificationService $classificationService
     * @param SitemapService $sitemapService
     * @param Domain $domain
     * @param CacheService $cacheService
     * @return Response
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws NotFoundApiException
     */
    public function advertisementAction(
        string $location,
        string $classification,
        string $date,
        LocationService $locationService,
        ClassificationService $classificationService,
        SitemapService $sitemapService,
        Domain $domain,
        CacheService $cacheService
    )
    {
        $tagAwareAdapter = $cacheService->getTagAwareAdapterInstanceForFileSystem();
        $advertisementSitemapCache = $tagAwareAdapter->getItem(CacheUtil::getKeySitemapAdvertisementDomain(
            $domain,
            $location,
            $classification,
            $date
        ));
        if (!$advertisementSitemapCache->isHit()) {
            try {
                $classificationInst = $classificationService->slugToInstance($classification);
                $locationInst = $locationService->slugToInstance($location);
                $date = new \DateTime($date);
            } catch (\Exception $e) {
                throw new NotFoundApiException($e->getMessage());
            }
            $advertisementSitemapXml = $this->render('sitemap/advertisement.xml.twig',
                [
                    'advertisements' => $sitemapService->getAdvertisementsByLocationAndClassificationAndDate($locationInst, $classificationInst, $date)
                ]
            )->getContent();
            $advertisementSitemapCache->set($advertisementSitemapXml)->tag(CacheUtil::getTagAdvertisements());
            $tagAwareAdapter->save($advertisementSitemapCache);
        } else {
            $advertisementSitemapXml = $advertisementSitemapCache->get();
        }
        return new Response($advertisementSitemapXml, 200, ['Content-Type' => 'application/xml']);
    }
}