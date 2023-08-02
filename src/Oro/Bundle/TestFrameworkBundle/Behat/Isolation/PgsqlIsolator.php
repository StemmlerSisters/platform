<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Isolation;

use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterFinishTestsEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeStartTestsEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\RestoreStateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Restore and backup PostgreSQL database between features
 */
class PgsqlIsolator implements IsolatorInterface
{
    private string $dbHost = '';
    private string $dbPort = '';
    private string $dbName = '';
    private string $dbPass = '';
    private string $dbUser = '';
    private string $dbTemp = '';
    private ContainerInterface $container;

    public function __construct(KernelInterface $kernel)
    {
        $kernel->boot();

        $this->container = $kernel->getContainer();
        $this->setupDatabaseUrl();
    }

    /** {@inheritdoc} */
    public function getTag()
    {
        return 'database';
    }

    /** {@inheritdoc} */
    public function getName()
    {
        return 'PostgreSQL DB';
    }

    /** {@inheritdoc} */
    public function isApplicable(ContainerInterface $container)
    {
        return true;
    }

    /** {@inheritdoc} */
    public function start(BeforeStartTestsEvent $event)
    {
        $event->writeln('<info>Dumping current application database</info>');
        $this->dump();
    }

    /** {@inheritdoc} */
    public function beforeTest(BeforeIsolatedTestEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function afterTest(AfterIsolatedTestEvent $event)
    {
        if (!$this->dbTemp) {
            return;
        }
        $event->writeln('<info>Restore database from dump</info>');
        $this->dropDb($this->dbName);
        $this->restore();
    }

    /** {@inheritdoc} */
    public function restoreState(RestoreStateEvent $event)
    {
        if (!$this->dbTemp) {
            return;
        }

        $event->writeln('<info>Begin to restore the state of Db...</info>');
        $event->writeln('<info>Drop Db</info>');
        $this->dropDb($this->dbName);

        $event->writeln('<info>Restore Db from dump</info>');
        $this->restore();
        $event->writeln('<info>Db was restored from dump</info>');

        $event->writeln('<info>Remove Db dump</info>');
        $this->dropDb($this->dbTemp);
    }

    private function setupDatabaseUrl(): void
    {
        $databaseUrl = $this->container->getParameter('database_dsn');
        $parsedUrl = parse_url($databaseUrl);

        if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'], $parsedUrl['path'])) {
            throw new \RuntimeException('Invalid database DSN provided');
        }

        $this->dbName = ltrim($parsedUrl['path'], '/');

        if ($parsedUrl['host'] === 'localhost' && isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            $this->dbHost = $queryParams['host'] ?? $parsedUrl['host'];
        } else {
            $this->dbHost = $parsedUrl['host'];
        }

        $this->dbPort = $parsedUrl['port'] ?? '';
        $this->dbUser = $parsedUrl['user'] ?? '';
        $this->dbPass = $parsedUrl['pass'] ?? '';
    }


    /** {@inheritdoc} */
    public function terminate(AfterFinishTestsEvent $event)
    {
        if (!$this->dbTemp) {
            return;
        }

        $event->writeln('<info>Remove Db dump</info>');
        $this->dropDb($this->dbTemp);
    }

    /** {@inheritdoc} */
    public function isOutdatedState()
    {
        return (bool)$this->dbTemp;
    }

    private function getPdoConnection(string $dbName = 'template1'): \PDO
    {
        $portSegment = ($this->dbPort !== '') ? sprintf("port=%s;", $this->dbPort) : '';

        $dsn = sprintf(
            'pgsql:host=%s;%sdbname=%s;user=%s;password=%s',
            $this->dbHost,
            $portSegment,
            $dbName,
            $this->dbUser,
            $this->dbPass
        );

        return new \PDO($dsn, $this->dbUser, $this->dbPass);
    }

    private function killConnections(): void
    {
        foreach ($this->container->get('doctrine')->getManagers() as $manager) {
            $manager->getConnection()->close();
        }

        $this->getPdoConnection()->exec(
            sprintf(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity ' .
                'WHERE datname in (\'%s\', \'%s\') AND pid <> pg_backend_pid()',
                $this->dbTemp,
                $this->dbName
            )
        );
    }

    private function dropDb(string $dbName): void
    {
        $attempts = 1;
        while (true) {
            try {
                $this->killConnections();

                $pdo = $this->getPdoConnection();
                $pdo->exec(\sprintf("DROP DATABASE IF EXISTS %s", $dbName));
            } catch (\Exception $e) {
                if ($attempts < 5) {
                    $attempts++;
                    continue;
                }
                throw $e;
            }
            break;
        }
    }

    private function createDB(string $dbName, string $template): void
    {
        $this->killConnections();

        $pdo = $this->getPdoConnection();
        $pdo->exec(sprintf("CREATE DATABASE %s WITH TEMPLATE %s OWNER %s", $dbName, $template, $this->dbUser));
    }

    /** {@inheritdoc} */
    private function dump(): void
    {
        $this->dbTemp = $this->dbName.TokenGenerator::generateToken('db');

        $this->createDB($this->dbTemp, $this->dbName);
    }

    /** {@inheritdoc} */
    private function restore(): void
    {
        $this->createDB($this->dbName, $this->dbTemp);
    }
}
