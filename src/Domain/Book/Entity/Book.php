<?php

declare(strict_types=1);

namespace App\Domain\Book\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[UniqueEntity('inv_number')]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, unique: true, nullable: false)]
    #[Assert\NotBlank]
    private string $inv_number;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    private string $author;

    public function __construct(
        string $inv_number,
        string $title,
        string $author
    ) {
        $this->inv_number = $inv_number;
        $this->title = $title;
        $this->author = $author;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function invNumber(): string
    {
        return $this->inv_number;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }
}
