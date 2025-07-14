<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Form\EventListener\ItemIdentifierCollectionTypeSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Test\FormInterface;

class ItemIdentifierCollectionTypeSubscriberTest extends TestCase
{
    private ItemIdentifierCollectionTypeSubscriber $subscriber;

    #[\Override]
    protected function setUp(): void
    {
        $this->subscriber = new ItemIdentifierCollectionTypeSubscriber('iso2Code');
    }

    private function getFormEvent(array $existingData, mixed $submittedData): FormEvent
    {
        $collection = new ArrayCollection($existingData);
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::any())
            ->method('getData')
            ->willReturn($collection);

        return new FormEvent($form, $submittedData);
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertEquals(
            [
                FormEvents::PRE_SUBMIT => ['preSubmit', -10]
            ],
            ItemIdentifierCollectionTypeSubscriber::getSubscribedEvents()
        );
    }

    public function testNoSubmittedData(): void
    {
        $event = $this->getFormEvent([new Country('c1')], null);

        $this->subscriber->preSubmit($event);

        self::assertNull($event->getData());
    }

    public function testEmptySubmittedData(): void
    {
        $event = $this->getFormEvent([new Country('c1')], []);

        $this->subscriber->preSubmit($event);

        self::assertSame([], $event->getData());
    }

    public function testEmptyExistingData(): void
    {
        $submittedData = [
            ['name' => 'item1'],
        ];
        $event = $this->getFormEvent(
            [],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals($submittedData, $event->getData());
    }

    public function testAtLeastOneItemInSubmittedDataHasId(): void
    {
        $submittedData = [
            ['name' => 'item1'],
            ['name' => 'item2', 'iso2Code' => 'c2'],
        ];
        $event = $this->getFormEvent(
            [new Country('c1')],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals($submittedData, $event->getData());
    }

    public function testAddItem(): void
    {
        $submittedData = [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ];
        $event = $this->getFormEvent(
            [new Country('c1')],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals(
            [
                ['name' => 'item1', 'iso2Code' => 'c1'],
                ['name' => 'item2'],
            ],
            $event->getData()
        );
    }

    public function testRemoveItem(): void
    {
        $submittedData = [
            ['name' => 'item1'],
        ];
        $event = $this->getFormEvent(
            [new Country('c1'), new Country('c2')],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals(
            [
                ['name' => 'item1', 'iso2Code' => 'c1'],
            ],
            $event->getData()
        );
    }

    public function testUpdateItem(): void
    {
        $submittedData = [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ];
        $event = $this->getFormEvent(
            [new Country('c1'), new Country('c2')],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals(
            [
                ['name' => 'item1', 'iso2Code' => 'c1'],
                ['name' => 'item2', 'iso2Code' => 'c2'],
            ],
            $event->getData()
        );
    }

    public function testRemoveNotLastItem(): void
    {
        $submittedData = [
            ['name' => 'item2'],
        ];
        $event = $this->getFormEvent(
            [new Country('c1'), new Country('c2')],
            $submittedData
        );

        $this->subscriber->preSubmit($event);

        self::assertEquals(
            [
                ['name' => 'item2', 'iso2Code' => 'c1'],
            ],
            $event->getData()
        );
    }
}
