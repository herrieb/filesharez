<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Account\ProfileFormType;
use App\Form\Account\PasswordFormType;
use App\Service\LibraryAccessService;
use App\Theme\ThemeRegistry;
use App\Theme\ThemeStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ThemeRegistry $themeRegistry,
        private ThemeStore $themeStore,
        private LibraryAccessService $accessService,
    ) {
    }

    #[Route('/', name: 'app_account')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile', name: 'app_account_profile')]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form->createView(),
            'allThemes' => $this->themeRegistry->all(),
            'user' => $user,
        ]);
    }

    #[Route('/security', name: 'app_account_security')]
    public function security(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(PasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $this->entityManager->flush();
            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute('app_account_security');
        }

        return $this->render('account/security.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/theme', name: 'app_account_theme_update', methods: ['POST'])]
    public function updateTheme(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $theme = (string) $request->request->get('theme', '');
        if ($theme === '' || $this->themeRegistry->get($theme) === null) {
            $this->addFlash('error', 'Unknown theme: ' . $theme);
            return $this->redirectToRoute('app_account_profile');
        }

        $user->setTheme($theme);
        $this->entityManager->flush();
        $this->addFlash('success', 'Theme updated.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'theme' => $theme]);
        }

        return $this->redirectToRoute('app_account_profile');
    }

    #[Route('/profile/theme/{id}/download', name: 'app_account_theme_download', methods: ['GET'])]
    public function downloadTheme(string $id): Response
    {
        $theme = $this->themeRegistry->get($id);
        if ($theme === null) {
            throw $this->createNotFoundException('Theme not found');
        }

        $zipPath = $this->themeStore->buildZip($theme);
        $filename = $theme->id . '.zip';

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'application/zip');
        return $response;
    }

    #[Route('/profile/theme/upload', name: 'app_account_theme_upload', methods: ['POST'])]
    public function uploadTheme(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $file = $request->files->get('theme');
        if ($file === null) {
            $this->addFlash('error', 'No file uploaded.');
            return $this->redirectToRoute('app_account_profile');
        }

        $tmp = $file->getPathname();
        try {
            $theme = $this->themeStore->installFromZip($tmp, $user);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Theme upload failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_account_profile');
        }

        $this->themeRegistry->reloadFromDisk();
        $user->setTheme($theme->id);
        $this->entityManager->flush();
        $this->addFlash('success', 'Theme "' . $theme->displayName . '" installed.');

        return $this->redirectToRoute('app_account_profile');
    }

    #[Route('/profile/theme/{id}/delete', name: 'app_account_theme_delete', methods: ['POST'])]
    public function deleteTheme(string $id, Request $request): Response
    {
        $csrf = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('theme_delete_' . $id, $csrf)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_account_profile');
        }

        try {
            $this->themeStore->delete($id);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Delete failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_account_profile');
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($user->getTheme() === $id) {
            $user->setTheme('longhorn');
            $this->entityManager->flush();
        }
        $this->themeRegistry->reloadFromDisk();
        $this->addFlash('success', 'Theme deleted.');
        return $this->redirectToRoute('app_account_profile');
    }

    #[Route('/library-activity', name: 'app_account_library_activity')]
    public function libraryActivity(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $logs = $this->accessService->recentForUser($user, 100);

        return $this->render('account/library_activity.html.twig', [
            'logs' => $logs,
        ]);
    }
}
