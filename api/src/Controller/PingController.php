<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_', format: 'json')]                // ①
final class PingController extends AbstractController         // ②
{
    #[Route('/ping', name: 'ping', methods: ['GET'])]         // ③
    public function ping(): JsonResponse                      // ④
    {
        return $this->json(['status' => 'ok', 'league' => 'GG League']);   // ⑤
    }

    #[Route('/hello/{name}', name: 'hello', methods: ['GET'], requirements: ['name' => '[A-Za-z0-9_]{2,30}'])]
    public function hello(string $name): JsonResponse      // {name} dans l'URL → argument $name (même nom)
    {
        return $this->json(["message" => "GG Zer0Day !"]);
    }
}
