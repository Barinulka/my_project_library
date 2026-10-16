<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Api\Client;

use App\Domain\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\ClientRepository;
use App\Presentation\Http\Response\Client\ClientResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientListController extends AbstractController
{
    #[Route('/api/clients', name: 'api.client_list', methods: ['GET'], format: 'json')]
    public function __invoke(
        ClientRepository $repository,
    ): JsonResponse {
        $clients = $repository->findAll();

        $response = array_map(fn (Client $client) => new ClientResponse(
                id: $client->id(),
                name: $client->name(),
                email: $client->email(),
            ), $clients);

        return $this->json($response, Response::HTTP_OK);
    }
}
