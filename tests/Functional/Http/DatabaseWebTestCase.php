<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $browser;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = self::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

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
