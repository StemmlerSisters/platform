<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Command;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\Command\UpdateCommand;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\EntityExtendUpdateProcessor;
use Oro\Bundle\EntityExtendBundle\Extend\EntityExtendUpdateProcessorResult;
use Oro\Component\Testing\Command\CommandTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateCommandTest extends TestCase
{
    use CommandTestingTrait;

    private EntityExtendUpdateProcessor&MockObject $entityExtendUpdateProcessor;
    private ConfigManager&MockObject $configManager;
    private UpdateCommand $command;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityExtendUpdateProcessor = $this->createMock(EntityExtendUpdateProcessor::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->command = new UpdateCommand($this->entityExtendUpdateProcessor, $this->configManager);
    }

    public function testExecuteSuccess(): void
    {
        $this->entityExtendUpdateProcessor->expects(self::once())
            ->method('processUpdate')
            ->willReturn(new EntityExtendUpdateProcessorResult(true));

        $commandTester = $this->doExecuteCommand($this->command);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains(
            $commandTester,
            'Updating the database schema and all entity extend related caches ...'
        );
        $this->assertOutputContains($commandTester, 'The update complete.');
    }

    public function testExecuteFailed(): void
    {
        $this->entityExtendUpdateProcessor->expects(self::once())
            ->method('processUpdate')
            ->willReturn(new EntityExtendUpdateProcessorResult(false));

        $commandTester = $this->doExecuteCommand($this->command);

        $this->assertOutputContains(
            $commandTester,
            'Updating the database schema and all entity extend related caches ...'
        );
        $this->assertProducedError($commandTester, 'The update failed.');
    }

    public function testExecuteWithDryRunAndNoChanges(): void
    {
        $entityConfig = new Config(
            new EntityConfigId('extend', 'Test\Entity'),
            ['is_extend' => true, 'state' => ExtendScope::STATE_ACTIVE]
        );
        $this->configManager->expects(self::once())
            ->method('getConfigs')
            ->with('extend')
            ->willReturn([$entityConfig]);

        $this->entityExtendUpdateProcessor->expects(self::never())
            ->method('processUpdate');

        $commandTester = $this->doExecuteCommand($this->command, ['--dry-run' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'There are no changes that require the database schema update.');
    }

    public function testExecuteWithDryRunAndHaveChanges(): void
    {
        $entityConfig = new Config(
            new EntityConfigId('extend', 'Test\Entity'),
            ['is_extend' => true, 'state' => ExtendScope::STATE_UPDATE]
        );
        $fieldConfig1 = new Config(
            new FieldConfigId('extend', 'Test\Entity', 'field1'),
            ['is_extend' => true, 'state' => ExtendScope::STATE_ACTIVE]
        );
        $fieldConfig2 = new Config(
            new FieldConfigId('extend', 'Test\Entity', 'field2'),
            ['is_extend' => true, 'state' => ExtendScope::STATE_UPDATE]
        );
        $this->configManager->expects(self::exactly(2))
            ->method('getConfigs')
            ->willReturnMap([
                ['extend', null, false, [$entityConfig]],
                ['extend', 'Test\Entity', false, [$fieldConfig1, $fieldConfig2]]
            ]);

        $this->entityExtendUpdateProcessor->expects(self::never())
            ->method('processUpdate');

        $commandTester = $this->doExecuteCommand($this->command, ['--dry-run' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'The following entities have changes');
        $this->assertOutputContains($commandTester, 'Test\Entity Requires update');
        $this->assertOutputContains($commandTester, 'field2 Requires update');
        $this->assertOutputNotContains($commandTester, 'field1');
    }
}
