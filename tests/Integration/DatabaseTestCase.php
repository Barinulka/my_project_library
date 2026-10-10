<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    // Выполняется перед каждым тестом и открывает транзакцию.
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    // Выполняется после теста и откатывает изменения.
    protected function tearDown(): void
    {
        try {
            if (isset($this->entityManager)) {
                $connection = $this->entityManager->getConnection();

                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }

                $this->entityManager->close();
            }
        } finally {
            parent::tearDown();
        }
    }
}
