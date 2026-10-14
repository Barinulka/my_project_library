<?php

declare(strict_types=1);

namespace App\Presentation\Http\Request\Book;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBookRequest
{
    #[Assert\NotBlank(
        message: 'Не может быть пустым'
    )]
    public string $inv_number;

    #[Assert\NotBlank(
        message: 'Не может быть пустым'
    )]
    public string $title;

    #[Assert\NotBlank(
        message: 'Не может быть пустым'
    )]
    public string $author;

    public function __construct(
        string $inv_number,
        string $title,
        string $author
    ) {
        $this->inv_number = trim($inv_number);
        $this->title = trim($title);
        $this->author = trim($author);
    }
}
