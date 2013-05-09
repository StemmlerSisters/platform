<?php

namespace Oro\Bundle\SoapBundle\Entity\Manager;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

class ApiEntityManager
{
    /**
     * @var string
     */
    protected $class;

    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    /**
     * Constructor
     *
     * @param string $class Entity name
     * @param ObjectManager $om Object manager
     */
    public function __construct($class, ObjectManager $om)
    {
        $this->metadata = $om->getClassMetadata($class);

        $this->class = $this->metadata->getName();
        $this->om = $om;
    }

    /**
     * Get entity metadata
     *
     * @return ClassMetadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Create new entity instance
     *
     * @return mixed
     */
    public function createEntity()
    {
        return new $this->class;
    }

    /**
     * Get entity by identifier.
     *
     * @param mixed $id
     * @return object
     */
    public function find($id)
    {
        return $this->getRepository()->find($id);
    }

    /**
     * Return related repository
     *
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->class);
    }

    /**
     * Retrieve object manager
     *
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->om;
    }

    /**
     * Returns Paginator to paginate throw items.
     *
     * In case when limit and offset set to null QueryBuilder instance will be returned.
     *
     * @param int $limit
     * @param int $offset
     * @param null $orderBy
     * @return \Traversable
     */
    public function getList($limit = 10, $offset = 1, $orderBy = null)
    {
        $orderBy = $orderBy ? $orderBy : $this->getDefaultOrderBy();
        return $this->getRepository()->findBy(array(), $orderBy, $limit, $offset);
    }

    /**
     * Get default order by.
     *
     * @return array|null
     */
    protected function getDefaultOrderBy()
    {
        $ids = $this->metadata->getIdentifierFieldNames();
        $orderBy = $ids ? array() : null;
        foreach ($ids as $pk) {
            $orderBy[$pk] = 'ASC';
        }
        return $orderBy;
    }
}
