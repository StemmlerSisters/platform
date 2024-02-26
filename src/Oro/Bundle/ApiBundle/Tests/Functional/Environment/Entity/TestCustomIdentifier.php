<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\TestFrameworkBundle\Entity\TestFrameworkEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: 'test_api_custom_id')]
class TestCustomIdentifier implements TestFrameworkEntityInterface
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public ?int $id = null;

    #[ORM\Column(name: '`key`', type: Types::STRING, unique: true, nullable: false)]
    public ?string $key = null;

    #[ORM\Column(name: 'name', type: Types::STRING, nullable: true)]
    public ?string $name = null;

    #[ORM\ManyToOne(targetEntity: TestCustomIdentifier::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    protected ?TestCustomIdentifier $parent = null;

    /**
     * @var Collection<int, TestCustomIdentifier>
     */
    #[ORM\ManyToMany(targetEntity: TestCustomIdentifier::class)]
    #[ORM\JoinTable(name: 'test_api_custom_id_children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id')]
    protected ?Collection $children = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    /**
     * @return TestCustomIdentifier|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param TestCustomIdentifier $item
     */
    public function setParent($item)
    {
        $this->parent = $item;
    }

    /**
     * @return Collection<int, TestCustomIdentifier>
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function addChild(TestCustomIdentifier $item)
    {
        $this->children->add($item);
    }

    public function removeChild(TestCustomIdentifier $item)
    {
        $this->children->removeElement($item);
    }
}
