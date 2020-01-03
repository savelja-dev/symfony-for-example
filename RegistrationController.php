<?php

namespace App\Controller;

use App\Entity\SocialUser;
use App\Entity\User;
use App\Exception\AccessDeniedApiException;
use App\Exception\ApiException;
use App\Exception\FormApiException;
use App\Exception\NotFoundApiException;
use App\Form\RegistrationType;
use App\Form\SocialUserType;
use App\Helper\Domain;
use App\Helper\Events\SendEmailEvent;
use App\Helper\FormData;
use App\Repository\SocialUserRepository;
use App\Repository\UserRepository;
use App\Response\ApiResponse;
use App\Service\CryptService;
use App\Service\MediaService;
use App\Service\UserService;
use App\Utils\ServerDateTimeUtil;
use App\Utils\SocialUserUtil;
use App\Utils\UserUtil;
use Doctrine\ORM\NonUniqueResultException;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


class RegistrationController extends AbstractController
{
    /**
     * Registration.
     * @SWG\Tag(name="security")
     * @SWG\Parameter(
     *     name="registration",
     *     in="body",
     *     description="Registration data",
     *     required=true,
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="email", type="string"),
     *         @SWG\Property(property="password", type="string"),
     *         @SWG\Property(property="recaptcha", type="string"),
     *     )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the status.",
     *     @SWG\Schema(
     *          type="object",
     *          @SWG\Property(property="id", type="integer"),
     *          @SWG\Property(property="email", type="string"),
     *          @SWG\Property(property="password", type="string"),
     *     )
     * )
     * @param FormData $formData
     * @param SerializerInterface $serializer
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param UserService $userService
     * @param UserRepository $userRepository
     * @param Domain $domain
     * @param EventDispatcherInterface $eventDispatcher
     * @return ApiResponse
     * @throws AccessDeniedApiException
     * @throws ApiException
     * @throws FormApiException
     * @throws NonUniqueResultException
     */
    public function apiRegistrationAction(
        FormData $formData,
        SerializerInterface $serializer,
        UserPasswordEncoderInterface $passwordEncoder,
        UserService $userService,
        UserRepository $userRepository,
        Domain $domain,
        EventDispatcherInterface $eventDispatcher
    )
    {
        /* @var $user User */
        if (($user = $userRepository->findOneBy(['email' => $formData->getValue('email')]))) {
            if ($user->hasRole('ROLE_USER')) {
                if($user->getSocialUsers()->first()){
                    throw new AccessDeniedApiException('You have an existing account. Please use your Google or Facebook account to sign in. If you require assistance please contact support@');
                }
                throw new AccessDeniedApiException('User with this email is registered already. If you require assistance please contact support@');
            }
        } else {
            $user = new User();
        }
        $form = $this->createForm(RegistrationType::class, $user);
        $form->submit($formData->getData());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new FormApiException($form);
        }
        $user->setPassword($passwordEncoder->encodePassword($user, $form->get('password')->getData()));
        $user->setName($userService->generateName($form->get('email')->getData()));
        $user->setRoles(['ROLE_USER']);
        $user->setStatus(UserUtil::STATUS_PENDING_EMAIL_CONFIRMATION);
        $now = ServerDateTimeUtil::createCurrent();
        $user->setModifiedAt($now);
        $user->setCreatedAt($now);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($user);
        $event = new SendEmailEvent(
            EmailUtil::TEMPLATE_ID_REGISTRATION,
            [
                'user' => $user,
                'domain' => $domain,
            ]
        );
        $eventDispatcher->dispatch($event, $event::NAME);
        $entityManager->flush();
        return new ApiResponse($serializer->serialize(
            [
                'id' => $user->getId(),
                'email' => $form->get('email')->getData(),
                'password' => $form->get('password')->getData(),
            ],
            'json'
        ));
    }

    /**
     * Authorization social user.
     * @SWG\Tag(name="security")
     * @SWG\Parameter(
     *     name="auth",
     *     in="body",
     *     description="Authorization data",
     *     required=true,
     *     @SWG\Schema(
     *          type="object",
     *          @SWG\Property(property="email", type="string", example="tech2+3@gmail.com"),
     *          @SWG\Property(property="id", type="string", example="1234567890"),
     *          @SWG\Property(property="idToken", type="string"),
     *          @SWG\Property(property="image", type="string", example="https://i.e.com/174202/profile/546e2a29-1489-4463-8fa9-8a27249f98c3_profile.jpg"),
     *          @SWG\Property(property="photoUrl", type="string", example=""),
     *          @SWG\Property(property="name", type="string", example="User Name"),
     *          @SWG\Property(property="firstName", type="string", example="User"),
     *          @SWG\Property(property="lastName", type="string", example="Name"),
     *          @SWG\Property(property="provider", type="string", example="facebook"),
     *          @SWG\Property(property="token", type="string", example="access_token_123abcd"),
     *          @SWG\Property(property="authToken", type="string", example=""),
     *     )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns the status.",
     *     @SWG\Schema(
     *          type="object",
     *         @SWG\Property(property="token", type="string"),
     *         @SWG\Property(property="refresh_token", type="string"),
     *     )
     * )
     * @param FormData $formData
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param UserService $userService
     * @param AuthenticationSuccessHandler $authenticationSuccessHandler
     * @param SocialUserRepository $socialUserRepository
     * @param UserRepository $userRepository
     * @param MediaService $mediaService
     * @param EventDispatcherInterface $eventDispatcher
     * @return JWTAuthenticationSuccessResponse
     * @throws ApiException
     * @throws FormApiException
     * @throws NonUniqueResultException
     * @throws NotFoundApiException
     * @throws ServerException
     */
    public function apiAuthSocialUserAction(
        FormData $formData,
        UserPasswordEncoderInterface $passwordEncoder,
        UserService $userService,
        AuthenticationSuccessHandler $authenticationSuccessHandler,
        SocialUserRepository $socialUserRepository,
        UserRepository $userRepository,
        MediaService $mediaService,
        EventDispatcherInterface $eventDispatcher
    )
    {
        // TODO: Need to be checked and reduced to some properties
        $form = $this->createForm(SocialUserType::class);
        $form->submit($formData->getData([FormData::FILTER_LOWERCASE => ['provider']]));
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new FormApiException($form);
        }
        try {
            SocialUserUtil::validate(
                $form->get('provider')->getData(),
                $form->get('token')->getData() ?? $form->get('authToken')->getData(),
                $form->get('id')->getData()
            );
        } catch (\Exception $ex) {
            throw new NotFoundApiException($ex->getMessage());
        }
        if (!($socialUser = $socialUserRepository->findOneBy(['id' => $form->get('id')->getData()]))) {
            $entityManager = $this->getDoctrine()->getManager();
            if (!($user = $userRepository->findOneBy(['email' => $form->get('email')->getData()]))) {
                $user = new User();
                $user->setEmail($form->get('email')->getData());
                $user->setPassword($passwordEncoder->encodePassword($user, $userService->randomPassword()));
                $user->setName($userService->generateName($user->getEmail()));
                $user->setRoles(['ROLE_USER']);
                $user->setStatus(UserUtil::STATUS_EMAIL_CONFIRMED);
                $now = ServerDateTimeUtil::createCurrent();
                $user->setModifiedAt($now);
                $user->setCreatedAt($now);
                $entityManager->persist($user);
                if (($imagePath = SocialUserUtil::getImagePath($form))) {
                    $user->setMediaId($mediaService->createUserMediaFromFilePath($user, $imagePath));
                    $entityManager->persist($user);
                }
            } else {
                if (!$user->hasRole('ROLE_USER')) {
                    $user->setRoles(['ROLE_USER']);
                    $user->setStatus(UserUtil::STATUS_EMAIL_CONFIRMED);
                }
            }
            $socialUser = new SocialUser();
            $socialUser->setId($form->get('id')->getData());
            $entityManager->persist($socialUser);
            $event = new SendEmailEvent(
                EmailUtil::TEMPLATE_ID_REGISTRATION_SOCIAL,
                [
                    'user' => $user,
                    'socialProvider' => $form->get('provider')->getData(),
                ]
            );
            $eventDispatcher->dispatch($event, $event::NAME);
            $socialUser->setUser($user);
            $entityManager->flush();
        }
        return $authenticationSuccessHandler->handleAuthenticationSuccess($socialUser->getUser());
    }

    /**
     * Confirm new password.
     * @SWG\Parameter(
     *   name="hash",
     *   type="string",
     *   required=true,
     *   in="query"
     * )
     * @param Request $request
     * @param CryptService $cryptRepository
     * @param UserRepository $userRepository
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return RedirectResponse
     */
    public function confirmNewPasswordAction(
        Request $request,
        CryptService $cryptRepository,
        UserRepository $userRepository,
        UserPasswordEncoderInterface $passwordEncoder
    )
    {
        try {
            if (!($hash = $request->query->get('hash'))) {
                throw new \Exception("Advertisement hash not found.");
            }
            $params = explode("|", $cryptRepository->decrypt($hash));
            $userId = &$params[0];
            $newPassword = &$params[1];
        } catch (\Exception $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
        /* @var $user User */
        if (!($user = $userRepository->findOneBy(['id' => $userId]))) {
            throw new BadRequestHttpException("Not found user with id: {$userId}.");
        }
        $user->setPassword($passwordEncoder->encodePassword($user, $newPassword));
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();
        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * Confirm registration.
     * @SWG\Parameter(
     *   name="hash",
     *   type="string",
     *   required=true,
     *   in="query"
     * )
     * @param Request $request
     * @param CryptService $cryptService
     * @param UserRepository $userRepository
     * @return RedirectResponse
     */
    public function confirmEmailAction(Request $request, CryptService $cryptService, UserRepository $userRepository)
    {
        try {
            if (!($hash = $request->query->get('hash'))) {
                throw new \Exception("Advertisement hash not found.");
            }
            $email = $cryptService->decrypt($hash);
        } catch (\Exception $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
        /* @var $user User */
        $user = $userRepository->findOneBy(['email' => $email]);
        $user->setStatus(UserUtil::STATUS_EMAIL_CONFIRMED);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();
        return $this->redirect($this->generateUrl('home'));
    }
}