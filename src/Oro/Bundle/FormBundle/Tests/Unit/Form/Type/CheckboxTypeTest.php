<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\CheckboxType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType as ParentCheckboxType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class CheckboxTypeTest extends TestCase
{
    /**
     * @var CheckboxType
     */
    private $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new CheckboxType();
    }

    public function testGetParent(): void
    {
        $this->assertEquals(ParentCheckboxType::class, $this->type->getParent());
    }

    public function testGetName(): void
    {
        $this->assertEquals(CheckboxType::NAME, $this->type->getName());
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertEquals(CheckboxType::NAME, $this->type->getBlockPrefix());
    }

    /**
     * @dataProvider buildFormProvider
     */
    public function testBuildForm(string $data, ?string $expected): void
    {
        $eventDispatcher = new EventDispatcher();
        $builder = new FormBuilder(
            'name',
            \stdClass::class,
            $eventDispatcher,
            $this->createMock(FormFactoryInterface::class)
        );
        $this->type->buildForm($builder, []);

        $event = new FormEvent($this->createMock(FormInterface::class), $data);
        $eventDispatcher->dispatch($event, FormEvents::PRE_SUBMIT);
        self::assertEquals($expected, $event->getData());
    }

    public function buildFormProvider(): array
    {
        return [
            ['any value', 'any value'],
            ['0', null],
        ];
    }
}
