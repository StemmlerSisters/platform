<?php

namespace Oro\Bundle\EntityBundle\Api;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;
use Oro\Bundle\EntityBundle\Model\EntityFieldStructure;
use Oro\Bundle\EntityBundle\Model\EntityStructure;

/**
 * Converts an EntityStructure object to an array.
 */
class EntityStructureNormalizer
{
    private ValueNormalizer $valueNormalizer;

    public function __construct(ValueNormalizer $valueNormalizer)
    {
        $this->valueNormalizer = $valueNormalizer;
    }

    public function normalize(EntityStructure $entity, RequestType $requestType): array
    {
        return [
            'id'          => $entity->getId(),
            'label'       => $entity->getLabel(),
            'pluralLabel' => $entity->getPluralLabel(),
            'alias'       => $entity->getAlias(),
            'pluralAlias' => $entity->getPluralAlias(),
            'className'   => $entity->getClassName(),
            'icon'        => $entity->getIcon(),
            'fields'      => $this->normalizeFields($entity->getFields(), $requestType),
            'options'     => $entity->getOptions(),
            'routes'      => $entity->getRoutes()
        ];
    }

    /**
     * @param EntityFieldStructure[] $fields
     * @param RequestType            $requestType
     *
     * @return array
     */
    private function normalizeFields(array $fields, RequestType $requestType): array
    {
        $result = [];
        foreach ($fields as $field) {
            $relatedEntityName = $field->getRelatedEntityName();
            $relatedEntityType = $relatedEntityName
                ? ValueNormalizerUtil::tryConvertToEntityType($this->valueNormalizer, $relatedEntityName, $requestType)
                : null;

            $result[] = [
                'name'              => $field->getName(),
                'label'             => $field->getLabel(),
                'type'              => $field->getType(),
                'relationType'      => $field->getRelationType(),
                'relatedEntityName' => $relatedEntityName,
                'relatedEntityType' => $relatedEntityType,
                'options'           => $field->getOptions()
            ];
        }

        return $result;
    }
}
