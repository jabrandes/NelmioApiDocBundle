<?php

/*
 * This file is part of the NelmioApiDocBundle package.
 *
 * (c) Nelmio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nelmio\ApiDocBundle\PropertyDescriber;

use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;

class ArrayPropertyDescriber implements PropertyDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use NullablePropertyTrait;

    /** @var PropertyDescriberInterface[] */
    private $propertyDescribers;

    public function __construct(iterable $propertyDescribers = [])
    {
        $this->propertyDescribers = $propertyDescribers;
    }

    public function describe(array $types, OA\Schema $property, array $groups = null)
    {
        $valueType = $types[0]->getCollectionValueType();
        if (null === $valueType) {
            throw new \LogicException(sprintf('Property "%s" is an array, but its items type isn\'t specified. You can specify that by using the type `string[]` for instance or `@OA\Property(type="array", @OA\Items(type="string"))`.', $property->title));
        }

        $this->setNullableProperty($types[0], $property);

        $keyType = $types[0]->getCollectionKeyType();
        if (is_null($keyType)) {
            $property->type = 'array';
            $this->setNullableProperty($types[0], $property);
            $property = Util::getChild($property, OA\Items::class);

        } else {
            $property->type = 'object';
            $property = Util::getChild($property, OA\AdditionalProperties::class);
        }

        foreach ($this->propertyDescribers as $propertyDescriber) {
            if ($propertyDescriber instanceof ModelRegistryAwareInterface) {
                $propertyDescriber->setModelRegistry($this->modelRegistry);
            }
            if ($propertyDescriber->supports([$valueType])) {
                $propertyDescriber->describe([$valueType], $property, $groups);

                break;
            }
        }
    }

    public function supports(array $types): bool
    {
        return 1 === count($types) && $types[0]->isCollection();
    }
}
