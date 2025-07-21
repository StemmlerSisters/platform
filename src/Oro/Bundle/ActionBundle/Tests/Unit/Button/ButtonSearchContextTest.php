<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Button;

use Oro\Bundle\ActionBundle\Button\ButtonSearchContext;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;
use PHPUnit\Framework\TestCase;

class ButtonSearchContextTest extends TestCase
{
    use EntityTestCaseTrait;

    private ButtonSearchContext $buttonSearchContext;

    #[\Override]
    protected function setUp(): void
    {
        $this->buttonSearchContext = new ButtonSearchContext();
    }

    public function testProperties(): void
    {
        $this->assertPropertyAccessors(
            $this->buttonSearchContext,
            [
                ['routeName', 'test_route'],
                ['datagrid', 'test_grid'],
                ['referrer', 'test_ref'],
                ['group', 'test_group']
            ]
        );
    }

    /**
     * @dataProvider getSetEntityDataProvider
     */
    public function testGetSetEntity(mixed $entityId): void
    {
        $this->buttonSearchContext->setEntity('Class', $entityId);
        $this->assertSame('Class', $this->buttonSearchContext->getEntityClass());
        $this->assertSame($entityId, $this->buttonSearchContext->getEntityId());
    }

    public function getSetEntityDataProvider(): array
    {
        return [
            'simple int id' => [10],
            'simple string id' => [uniqid('', true)],
            'array id' => [[10, uniqid('', true)]]
        ];
    }

    public function testGetHash(): void
    {
        $this->buttonSearchContext->setEntity('Class', ['id' => 42])
            ->setRouteName('test_route')
            ->setDatagrid('test_datagrid')
            ->setReferrer('test_referrer')
            ->setGroup(['test_group1', 'test_groug2']);

        $this->assertEquals('654dfa2c4ef17b70a92ed9b7c0ffbc5a', $this->buttonSearchContext->getHash());
    }
}
