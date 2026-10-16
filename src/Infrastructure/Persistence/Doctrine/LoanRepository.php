<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Book\Entity\Book;
use App\Domain\Client\Entity\Client;
use App\Domain\Loan\Entity\Loan;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Loan> */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    public function save(Loan $loan): void
    {
        $this->getEntityManager()->persist($loan);
        $this->getEntityManager()->flush();
    }

    public function findOpenedLoan(Book $book): ?Loan
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->where('l.book = :book')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('book', $book);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return Loan[] */
    public function findOpenLoansByClient(Client $client): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->join('l.book', 'b')
            ->addSelect('b')
            ->where('l.client = :client')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('client', $client);

        return $qb->getQuery()->getResult();
    }

    public function countOpenLoansByClient(Client $client): int
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->select('COUNT(l.id)')
            ->where('l.client = :client')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('client', $client);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function hasOverdueLoansByClient(
        Client $client,
        DateTimeImmutable $now,
    ): bool
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->select('COUNT(l.id)')
            ->where('l.client = :client')
            ->andWhere('l.returned_at IS NULL')
            ->andWhere('l.planned_return_at < :now')
            ->setParameter('client', $client)
            ->setParameter('now', $now);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return Loan[] */
    public function findOverdueLoans(DateTimeImmutable $now): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->join('l.book', 'b')
            ->join('l.client', 'c')
            ->addSelect('b')
            ->addSelect('c')
            ->where('l.planned_return_at < :now')
            ->andWhere('l.returned_at IS NULL')
            ->setParameter('now', $now)
            ->orderBy('l.planned_return_at', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /** @return Loan[] */
    public function findHistoryByBook(Book $book): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->join('l.book', 'b')
            ->join('l.client', 'c')
            ->addSelect('b')
            ->addSelect('c')
            ->where('l.book = :book')
            ->setParameter('book', $book)
            ->orderBy('l.loan_at', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /** @return Loan[] */
    public function getOpenedLoansList(): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb
            ->join('l.book', 'b')
            ->join('l.client', 'c')
            ->addSelect('b')
            ->addSelect('c')
            ->where('l.returned_at IS NULL')
            ->orderBy('l.loan_at', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
