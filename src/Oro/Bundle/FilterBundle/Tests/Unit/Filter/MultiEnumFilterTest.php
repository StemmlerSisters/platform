<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\FilterBundle\Filter\MultiEnumFilter;
use Oro\Bundle\FilterBundle\Form\Type\Filter\EnumFilterType;
use Oro\Component\Testing\ReflectionUtil;
use Oro\Component\Testing\Unit\ORM\OrmTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Test\FormInterface;

class MultiEnumFilterTest extends OrmTestCase
{
    private EntityManagerInterface $em;
    private FormFactoryInterface&MockObject $formFactory;
    private MultiEnumFilter $filter;

    #[\Override]
    protected function setUp(): void
    {
        $this->em = $this->getTestEntityManager();
        $this->em->getConfiguration()->setMetadataDriverImpl(new AttributeDriver([]));

        $this->formFactory = $this->createMock(FormFactoryInterface::class);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn($this->em);

        $this->filter = new MultiEnumFilter(
            $this->formFactory,
            new FilterUtility(),
        );
    }

    public function testInit(): void
    {
        $this->filter->init('test', []);

        $params = ReflectionUtil::getPropertyValue($this->filter, 'params');

        self::assertEquals(
            [FilterUtility::FRONTEND_TYPE_KEY => 'dictionary', 'options' => []],
            $params
        );
    }

    public function testInitWithNullValue(): void
    {
        $this->filter->init('test', ['null_value' => ':empty:']);

        $params = ReflectionUtil::getPropertyValue($this->filter, 'params');

        self::assertEquals(
            [
                FilterUtility::FRONTEND_TYPE_KEY => 'dictionary',
                'null_value' => ':empty:',
                'options' => []
            ],
            $params
        );
    }

    public function testInitWithClass(): void
    {
        $this->filter->init('test', ['class' => 'Test\EnumValue']);

        $params = ReflectionUtil::getPropertyValue($this->filter, 'params');

        self::assertEquals(
            [
                FilterUtility::FRONTEND_TYPE_KEY => 'dictionary',
                'options' => [
                    'class' => 'Test\EnumValue'
                ]
            ],
            $params
        );
    }

    public function testInitWithEnumCode(): void
    {
        $this->filter->init('test', ['enum_code' => 'test_enum']);

        $params = ReflectionUtil::getPropertyValue($this->filter, 'params');

        self::assertEquals(
            [
                FilterUtility::FRONTEND_TYPE_KEY => 'dictionary',
                'options' => [
                    'enum_code' => 'test_enum',
                    'class' => 'Extend\Entity\EV_Test_Enum'
                ],
                'class' => 'Extend\Entity\EV_Test_Enum'
            ],
            $params
        );
    }

    public function testGetForm(): void
    {
        $form = $this->createMock(FormInterface::class);

        $this->formFactory->expects(self::once())
            ->method('create')
            ->with(EnumFilterType::class)
            ->willReturn($form);

        self::assertSame($form, $this->filter->getForm());
    }

    public function testPrepareData(): void
    {
        $data = [];
        self::assertSame($data, $this->filter->prepareData($data));
    }
}
