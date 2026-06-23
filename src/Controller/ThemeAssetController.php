<?php

namespace App\Controller;

use App\Theme\ThemeStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/themes')]
class ThemeAssetController extends AbstractController
{
    public function __construct(
        private ThemeStore $themeStore,
    ) {
    }

    #[Route('/{id}/{file}', name: 'app_theme_asset', requirements: ['id' => '[a-z0-9-]+', 'file' => '.+'])]
    public function asset(string $id, string $file): Response
    {
        $path = $this->themeStore->themeDir($id) . '/assets/' . $file;
        $real = realpath($path);
        $assetsRoot = realpath($this->themeStore->themeDir($id) . '/assets');
        if ($real === false || $assetsRoot === false || !str_starts_with($real, $assetsRoot . DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException('Asset not found');
        }
        if (!is_file($real)) {
            throw $this->createNotFoundException('Asset not found');
        }
        return new BinaryFileResponse($real);
    }
}
