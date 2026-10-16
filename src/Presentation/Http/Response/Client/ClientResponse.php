<?php

declare(strict_types=1);

namespace App\Presentation\Http\Response\Client;

final readonly class ClientResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
