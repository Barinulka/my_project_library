<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Application\Loan\IssueLoanHandler;
use App\Application\Loan\ReturnLoanHandler;
use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Infrastructure\Persistence\Doctrine\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-loan',
    description: 'Проверяет выдачу и возврат экземпляра книги',
)]
class CheckLoanCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueLoanHandler $issueLoanHandler,
        private ReturnLoanHandler $returnLoanHandler,
        private LoanRepository $loanRepository,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $suffix = bin2hex(random_bytes(8));

        $client = new Client(
            name: 'Учебный читатель',
            email: "reader-{$suffix}@example.com",
        );

        $book = new Book(
            inv_number: "TEST-{$suffix}",
            title: 'Учебная книга',
            author: 'Учебный автор',
        );

        $this->entityManager->persist($client);
        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $this->issueLoanHandler->handle($client, $book);

        $loan = $this->loanRepository->findOpenedLoan($book);

        if ($loan === null) {
            $io->error('После выдачи открытый Loan не найден.');

            return Command::FAILURE;
        }

        $io->table(
            ['Выдача', 'Дата выдачи', 'Срок возврата'],
            [[
                $loan->id(),
                $loan->loan_at()->format('d.m.Y H:i:s'),
                $loan->planned_return_at()->format('d.m.Y H:i:s'),
            ]],
        );

        $repeatIssueRejected = false;

        try {
            $this->issueLoanHandler->handle($client, $book);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'Экземпляр уже выдан') {
                throw $exception;
            }

            $repeatIssueRejected = true;
        }

        if (!$repeatIssueRejected) {
            $io->error('Повторная выдача ошибочно разрешена.');

            return Command::FAILURE;
        }

        $loanCount = $this->loanRepository->count(['book' => $book]);

        if ($loanCount !== 1) {
            $io->error('Для экземпляра ожидалась ровно одна выдача.');

            return Command::FAILURE;
        }

        $io->success('Повторная выдача отклонена, лишняя запись не создана.');

        $this->returnLoanHandler->handle($book);

        // Убираем объекты из памяти Doctrine, чтобы повторно
        // загрузить выдачу и проверить данные именно из базы.
        $loanId = $loan->id();
        $bookId = $book->id();

        $this->entityManager->clear();

        $savedBook = $this->entityManager->find(Book::class, $bookId);
        $savedLoan = $this->loanRepository->find($loanId);

        if (!$savedBook instanceof Book || !$savedLoan instanceof \App\Domain\Loan\Entity\Loan) {
            $io->error('Не удалось загрузить сохранённые данные.');

            return Command::FAILURE;
        }

        if ($this->loanRepository->findOpenedLoan($savedBook) !== null) {
            $io->error('После возврата выдача осталась открытой.');

            return Command::FAILURE;
        }

        if ($savedLoan->returned_at() === null) {
            $io->error('Дата фактического возврата не сохранена.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Выдача и возврат выполнены. Дата возврата: %s',
            $savedLoan->returned_at()->format('d.m.Y H:i:s'),
        ));

        return Command::SUCCESS;
    }
}
