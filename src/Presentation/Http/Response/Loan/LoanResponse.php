<?php

declare(strict_types=1);

namespace App\Presentation\Http\Response\Loan;

final readonly class LoanResponse
{
    public function __construct(
        public int $loan_id,
        public int $book_id,
        public int $client_id,
        public string $client_name,
        public string $inv_number,
        public string $title,
        public string $loan_at,
        public string $planned_return_at,
        public bool $overdue,
    ) {
    }
}
