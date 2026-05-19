<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ErrorController extends AbstractController
{
    public function show(\Throwable $exception, ?\Symfony\Component\HttpFoundation\Request $request = null): Response
    {
        $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;

        if ($statusCode === 404) {
            return $this->render('errors/404.html.twig', [], new Response('', 404));
        }

        if ($statusCode === 403) {
            return $this->render('errors/403.html.twig', [], new Response('', 403));
        }

        return $this->render('errors/500.html.twig', [], new Response('', 500));
    }
}