<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller\Api\Client;

use App\Application\Client\CreateClientHandler;
use App\Presentation\Http\Request\Client\CreateClientRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class CreateClientController extends AbstractController
{
    #[Route('/api/clients', name: 'api.client_create', methods: ['POST'], format: 'json')]
    public function __invoke(
        #[MapRequestPayload] CreateClientRequest $request,
        CreateClientHandler $clientHandler,
    ): JsonResponse {
        $client = $clientHandler->handle($request->name, $request->email);

        return $this->json(['id' => $client->id()], Response::HTTP_CREATED);
    }
}
