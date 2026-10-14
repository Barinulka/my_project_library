<?php

declare(strict_types=1);

namespace App\Presentation\Http\Request\Client;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateClientRequest
{
    #[Assert\NotBlank(
        message: 'Не может быть пустым'
    )]
    public string $name;

    #[Assert\NotBlank(
        message: 'Не может быть пустым'
    )]
    #[Assert\Email(
        message: 'Некорректный email'
    )]
    public string $email;

    public function __construct(
        string $name,
        string $email,
    ) {
        $this->name = trim($name);
        $this->email = trim($email);
    }
}
