<?php

namespace Oro\Bundle\MigrationBundle\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\CreateMigrationTableMigration;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\MigrationState;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\MigrationBundle\Migration\UpdateBundleVersionMigration;
use PHPUnit\Framework\TestCase;

class UpdateBundleVersionMigrationTest extends TestCase
{
    /**
     * @dataProvider upProvider
     */
    public function testUp(array $migrations, array $expectedUpdates): void
    {
        $queryBag = new QueryBag();
        $updateMigration = new UpdateBundleVersionMigration($migrations);
        $updateMigration->up(new Schema(), $queryBag);

        $assertQueries = [];
        foreach ($expectedUpdates as $bundleName => $version) {
            $assertQueries[] = sprintf(
                "INSERT INTO %s (bundle, version, loaded_at) VALUES ('%s', '%s',",
                CreateMigrationTableMigration::MIGRATION_TABLE,
                $bundleName,
                $version
            );
        }

        $this->assertEmpty($queryBag->getPreQueries());
        $postSqls = $queryBag->getPostQueries();
        foreach ($assertQueries as $index => $query) {
            $this->assertTrue(
                str_starts_with($postSqls[$index], $query),
                sprintf('Query index: %d. Query: %s', $index, $query)
            );
        }
    }

    public function upProvider(): array
    {
        return [
            'all success'           => [
                'migrations'      => [
                    $this->getMigration('testBundle', 'v1_0'),
                    $this->getMigration('testBundle', 'v1_1'),
                    $this->getMigration('test1Bundle', 'v1_0'),
                ],
                'expectedUpdates' => [
                    'testBundle'  => 'v1_1',
                    'test1Bundle' => 'v1_0'
                ]
            ],
            'first version failed'  => [
                'migrations'      => [
                    $this->getMigration('testBundle', 'v1_0', false),
                    $this->getMigration('testBundle', 'v1_1', null),
                    $this->getMigration('test1Bundle', 'v1_0'),
                ],
                'expectedUpdates' => [
                    'test1Bundle' => 'v1_0'
                ]
            ],
            'last version failed'   => [
                'migrations'      => [
                    $this->getMigration('testBundle', 'v1_0'),
                    $this->getMigration('testBundle', 'v1_1', false),
                    $this->getMigration('test1Bundle', 'v1_0'),
                ],
                'expectedUpdates' => [
                    'testBundle'  => 'v1_0',
                    'test1Bundle' => 'v1_0'
                ]
            ],
            'middle version failed' => [
                'migrations'      => [
                    $this->getMigration('testBundle', 'v1_0'),
                    $this->getMigration('testBundle', 'v1_1', false),
                    $this->getMigration('testBundle', 'v1_2', null),
                    $this->getMigration('test1Bundle', 'v1_0'),
                ],
                'expectedUpdates' => [
                    'testBundle'  => 'v1_0',
                    'test1Bundle' => 'v1_0'
                ]
            ],
        ];
    }

    private function getMigration(string $bundleName, string $version, ?bool $state = true): MigrationState
    {
        $migration = new MigrationState(
            $this->createMock(Migration::class),
            $bundleName,
            $version
        );
        if ($state === true) {
            $migration->setSuccessful();
        } elseif ($state === false) {
            $migration->setFailed();
        }

        return $migration;
    }
}
