<?php

declare(strict_types=1);

namespace App\Domain\Client\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[UniqueEntity('email')]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, unique: true, nullable: false)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private string $email;

    public function __construct(
        string $name,
        string $email
    ) {
        $this->name = $name;
        $this->email = $email;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }
}
