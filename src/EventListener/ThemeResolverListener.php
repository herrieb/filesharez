<?php

namespace App\EventListener;

use App\Entity\User;
use App\Theme\ThemeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 5)]
class ThemeResolverListener
{
    public function __construct(
        private ThemeRegistry $registry,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tokenUser = $this->security->getUser();

        $themeId = null;
        if ($tokenUser !== null) {
            $managed = $this->entityManager->find(User::class, $tokenUser->getId());
            $themeId = $managed?->getTheme();
        }

        $theme = $this->registry->resolveOrDefault($themeId);

        $request->attributes->set('_theme', $theme);
        $request->attributes->set('_theme_id', $theme->id);
    }
}
