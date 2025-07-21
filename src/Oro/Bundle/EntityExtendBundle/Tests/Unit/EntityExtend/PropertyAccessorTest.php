<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\EntityExtend;

use Oro\Bundle\EntityExtendBundle\EntityExtend\PropertyAccessorWithDotArraySyntax;
use Oro\Bundle\EntityExtendBundle\Extend\ReflectionExtractor;
use Oro\Bundle\EntityExtendBundle\PropertyAccess;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\PropertyAccess\TestClass;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\PropertyAccess\TestClassMagicCall;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\PropertyAccess\TestClassMagicGet;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\PropertyAccess\Ticket5775Object;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PropertyAccessorTest extends TestCase
{
    private PropertyAccessorInterface $propertyAccessor;

    #[\Override]
    protected function setUp(): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax();
    }

    public function getPathsWithUnexpectedType(): array
    {
        return [
            ['', 'foobar'],
            ['foo', 'foobar'],
            [null, 'foobar'],
            [123, 'foobar'],
            [(object) ['prop' => null], 'prop.foobar'],
            [(object) ['prop' => (object) ['subProp' => null]], 'prop.subProp.foobar'],
            [['index' => null], '[index][foobar]'],
            [['index' => ['subIndex' => null]], '[index][subIndex][foobar]'],
        ];
    }

    public function getPathsWithMissingProperty(): array
    {
        return [
            [(object)['firstName' => 'John'], 'lastName'],
            [(object)['property' => (object)['firstName' => 'John']], 'property.lastName'],
            [['index' => (object)['firstName' => 'John']], 'index.lastName'],
            [new TestClass('John'), 'protectedAccessor'],
            [new TestClass('John'), 'protectedIsAccessor'],
            [new TestClass('John'), 'protectedHasAccessor'],
            [new TestClass('John'), 'privateAccessor'],
            [new TestClass('John'), 'privateIsAccessor'],
            [new TestClass('John'), 'privateHasAccessor'],
            // Properties are not camelized
            [new TestClass('John'), 'public_property'],
        ];
    }

    public function getPathsWithMissingIndex(): array
    {
        return [
            [['firstName' => 'John'], 'lastName'],
            [[], 'index.lastName'],
            [['index' => []], 'index.lastName'],
            [['index' => ['firstName' => 'John']], 'index.lastName'],
            [(object)['property' => ['firstName' => 'John']], 'property.lastName'],
        ];
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue(object|array $objectOrArray, string $path, mixed $value): void
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPathsWhenIndexExceptionsDisabled
     */
    public function testGetValueWhenIndexExceptionsDisabled(
        object|array $objectOrArray,
        string $path,
        mixed $value
    ): void {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax();
        $this->assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    public function testGetValueThrowsExceptionForInvalidPropertyPathType(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue(new \stdClass(), 123);
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testGetValueThrowsExceptionIfPropertyNotFound(object|array $objectOrArray, string $path): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsDisabled(
        object|array $objectOrArray,
        string $path
    ): void {
        // When exceptions are disabled, non-existing indices can be read. In this case, null is returned
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessorWithDotArraySyntax::DO_NOT_THROW
        );
        $this->assertNull($this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsExceptionIfIndexNotFound(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            throw: PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    public function testGetValueThrowsExceptionIfNotArrayAccess(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue(new \stdClass(), 'index');
    }

    public function testGetValueReadsMagicGet(): void
    {
        $this->assertSame('John', $this->propertyAccessor->getValue(new TestClassMagicGet('John'), 'magicProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicGetThatReturnsConstant(): void
    {
        $this->assertSame(
            'constant value',
            $this->propertyAccessor->getValue(new TestClassMagicGet('John'), 'constantMagicProperty')
        );
    }

    public function testGetValueDoesNotReadMagicCallByDefault(): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue(new TestClassMagicCall('John'), 'magicCallProperty');
    }

    public function testGetValueReadsMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_GET);

        $this->assertSame(
            'John',
            $this->propertyAccessor->getValue(new TestClassMagicCall('John'), 'magicCallProperty')
        );
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicCallThatReturnsConstant(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_CALL);

        $this->assertSame(
            'constant value',
            $this->propertyAccessor->getValue(new TestClassMagicCall('John'), 'constantMagicCallProperty')
        );
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    public function testSetValueThrowsExceptionForInvalidPropertyPathType(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new \stdClass();

        $this->propertyAccessor->setValue($testObject, 123, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testSetValueThrowsExceptionIfPropertyNotFound(object|array $objectOrArray, string $path): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFound(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsDisabled(
        object|array $objectOrArray,
        string $path
    ): void {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    public function testSetValueThrowsExceptionIfNotArrayAccess(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new \stdClass();

        $this->propertyAccessor->setValue($testObject, 'index', 'Updated');
    }

    public function testSetValueThrowsExceptionIfThereIsInvalidItemInGraph(): void
    {
        $this->expectException(InvalidPropertyPathException::class);
        $objectOrArray = new \stdClass();
        $objectOrArray->root = ['index' => 123];

        $this->propertyAccessor->setValue($objectOrArray, 'root.index.firstName.', 'Updated');
    }

    public function testSetValueUpdatesMagicSet(): void
    {
        $author = new TestClassMagicGet('John');

        $this->propertyAccessor->setValue($author, 'magicProperty', 'Updated');

        $this->assertEquals('Updated', $author->__get('magicProperty'));
    }

    public function testSetValueThrowsExceptionIfThereAreMissingParameters(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new TestClass('John');

        $this->propertyAccessor->setValue($testObject, 'publicAccessorWithMoreRequiredParameters', 'Updated');
    }

    public function testSetValueDoesNotUpdateMagicCallByDefault(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $author = new TestClassMagicCall('John');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');
    }

    public function testSetValueUpdatesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_CALL);

        $author = new TestClassMagicCall('John');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        $this->assertEquals('Updated', $author->__call('getMagicCallProperty', []));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray(mixed $objectOrArray, string $path): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');

        $this->propertyAccessor->setValue($objectOrArray, $path, 'value');
    }

    /**
     * @dataProvider getValidPropertyPathsForRemove
     */
    public function testRemove(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            throw: PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->propertyAccessor->remove($objectOrArray, $path);

        if (count(func_get_args()) === 3) {
            $expectedValue = func_get_args()[2];
            $actualValue = $this->propertyAccessor->getValue($objectOrArray, $path);
            $this->assertSame($expectedValue, $actualValue);
        } else {
            try {
                $this->propertyAccessor->getValue($objectOrArray, $path);
                $this->fail(sprintf('It is expected that "%s" is removed.', $path));
            } catch (NoSuchPropertyException $ex) {
            }
        }
    }

    public function testRemoveThrowsExceptionForInvalidPropertyPathType(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new \stdClass();

        $this->propertyAccessor->remove($testObject, 123);
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testRemoveThrowsExceptionIfPropertyNotFound(object|array $objectOrArray, string $path): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->remove($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testRemoveThrowsNoExceptionIfIndexNotFound(object|array $objectOrArray, string $path): void
    {
        $clone = unserialize(serialize($objectOrArray));
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            ReflectionExtractor::ALLOW_MAGIC_CALL,
            PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->propertyAccessor->remove($objectOrArray, $path);

        try {
            $this->propertyAccessor->getValue($objectOrArray, $path);
            $this->fail(sprintf('It is expected that "%s" is removed.', $path));
        } catch (NoSuchPropertyException $ex) {
        }

        $this->assertEquals($clone, $objectOrArray);
    }

    public function testRemoveThrowsExceptionIfNotArrayAccess(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new \stdClass();

        $this->propertyAccessor->remove($testObject, 'index');
    }

    public function testRemoveThrowsExceptionIfThereIsInvalidItemInGraph(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $objectOrArray = new \stdClass();
        $objectOrArray->root = ['index' => 123];

        $this->propertyAccessor->remove($objectOrArray, 'root.index.firstName');
    }

    public function testRemoveUpdatesMagicUnset(): void
    {
        $author = new TestClassMagicGet('John');

        $this->propertyAccessor->remove($author, 'magicProperty');

        $this->assertNull($author->__get('magicProperty'));
    }

    public function testRemoveThrowsExceptionIfThereAreMissingParameters(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new TestClass('John');

        $this->propertyAccessor->remove($testObject, 'publicAccessorWithMoreRequiredParameters');
    }

    public function testRemoveThrowsExceptionIfPublicPropertyHasNoUnsetter(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new TestClass('John');

        $this->propertyAccessor->remove($testObject, 'publicProperty');
    }

    public function testRemoveThrowsExceptionIfProtectedProperty(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new TestClass('John');

        $this->propertyAccessor->remove($testObject, 'protectedProperty');
    }

    public function testRemoveThrowsExceptionIfPrivateProperty(): void
    {
        $this->expectException(NoSuchPropertyException::class);
        $testObject = new TestClass('John');

        $this->propertyAccessor->remove($testObject, 'privateProperty');
    }

    public function testRemoveDoesNotUpdateMagicCallByDefault(): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
        );
        $this->expectException(NoSuchPropertyException::class);
        $author = new TestClassMagicCall('John');

        $this->propertyAccessor->remove($author, 'magicCallProperty');
    }

    public function testRemoveUpdatesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_CALL);

        $author = new TestClassMagicCall('John');

        $this->propertyAccessor->remove($author, 'magicCallProperty');

        $this->assertNull($author->__call('getMagicCallProperty', []));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testRemoveThrowsExceptionIfNotObjectOrArray(mixed $objectOrArray, string $path): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');

        $this->propertyAccessor->remove($objectOrArray, $path);
    }

    public function testGetValueWhenArrayValueIsNull(): void
    {
        $this->assertNull(
            $this->propertyAccessor->getValue(['index' => ['nullable' => null]], 'index.nullable')
        );
    }

    public function testGetValueWhenArrayValueIsNullAndIndexExceptionsDisabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->assertNull(
            $this->propertyAccessor->getValue(['index' => ['nullable' => null]], 'index.nullable')
        );
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable(object|array $objectOrArray, string $path): void
    {
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPathsWhenIndexExceptionsDisabled
     */
    public function testIsReadableWhenIndexExceptionsDisabled(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessorWithDotArraySyntax::DO_NOT_THROW
        );
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS
        );
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsReadableReturnsFalseIfPropertyNotFound(object|array $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsFalseIfIndexNotFound(object|array $objectOrArray, string $path): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            throw: PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        // Non-existing indices cannot be read
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsTrueIfIndexNotFoundAndIndexExceptionsDisabled(
        object|array $objectOrArray,
        string $path
    ): void {
        // When exceptions are disabled, non-existing indices can be read.
        // In this case, null is returned by getValue method
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorWithDotSyntax(
            throw: PropertyAccessorWithDotArraySyntax::DO_NOT_THROW
        );
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    public function testIsReadableRecognizesMagicGet(): void
    {
        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicGet('John'), 'magicProperty'));
    }

    public function testIsReadableDoesNotRecognizeMagicCallByDefault(): void
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->assertFalse($this->propertyAccessor->isReadable(new TestClassMagicCall('John'), 'magicCallProperty'));
    }

    public function testIsReadableRecognizesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_GET);

        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicCall('John'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsReadableReturnsFalseIfNotObjectOrArray(mixed $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable(object|array $objectOrArray, string $path): void
    {
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsWritableReturnsFalseIfPropertyNotFound(object|array $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFound(object|array $objectOrArray, string $path): void
    {
        // Non-existing indices can be written. Arrays are created on-demand.
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFoundAndIndexExceptionsDisabled(
        object|array $objectOrArray,
        string $path
    ): void {
        // Non-existing indices can be written even if exceptions are enabled
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessorWithDotArraySyntax::THROW_ON_INVALID_INDEX
        );
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function testIsWritableRecognizesMagicSet(): void
    {
        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicGet('John'), 'magicProperty'));
    }

    public function testIsWritableDoesNotRecognizeMagicCallByDefault(): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable(new TestClassMagicCall('John'), 'magicCallProperty'));
    }

    public function testIsWritableRecognizesMagicCallIfEnabled(): void
    {
        $this->propertyAccessor = new PropertyAccessorWithDotArraySyntax(ReflectionExtractor::ALLOW_MAGIC_CALL);

        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicCall('John'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsWritableReturnsFalseIfNotObjectOrArray(mixed $objectOrArray, string $path): void
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function getValidPropertyPaths(): array
    {
        return [
            [['John', 'Doo'], '0', 'John'],
            [['John', 'Doo'], '1', 'Doo'],
            [['firstName' => 'John'], 'firstName', 'John'],
            [['firstName' => 'John'], '[firstName]', 'John'],
            [['index' => ['firstName' => 'John']], 'index.firstName', 'John'],
            [['index' => ['firstName' => 'John']], '[index][firstName]', 'John'],
            [['index' => ['firstName' => 'John']], '[index].firstName', 'John'],
            [['index' => ['firstName' => 'John']], 'index[firstName]', 'John'],
            [(object)['firstName' => 'John'], 'firstName', 'John'],
            [(object)['firstName' => 'John'], '[firstName]', 'John'],
            [(object)['property' => ['firstName' => 'John']], 'property.firstName', 'John'],
            [(object)['property' => ['firstName' => 'John']], '[property][firstName]', 'John'],
            [(object)['property' => ['firstName' => 'John']], '[property].firstName', 'John'],
            [(object)['property' => ['firstName' => 'John']], 'property[firstName]', 'John'],
            [['index' => (object)['firstName' => 'John']], 'index.firstName', 'John'],
            [(object)['property' => (object)['firstName' => 'John']], 'property.firstName', 'John'],

            // Accessor methods
            [new TestClass('John'), 'publicProperty', 'John'],
            [new TestClass('John'), 'publicAccessor', 'John'],
            [new TestClass('John'), 'publicAccessorWithDefaultValue', 'John'],
            [new TestClass('John'), 'publicAccessorWithRequiredAndDefaultValue', 'John'],
            [new TestClass('John'), 'publicIsAccessor', 'John'],
            [new TestClass('John'), 'publicHasAccessor', 'John'],
            [new TestClass('John'), 'publicGetSetter', 'John'],

            // Methods are camelized
            [new TestClass('John'), 'public_accessor', 'John'],
            [new TestClass('John'), '_public_accessor', 'John'],

            // Special chars
            [['%!@$§' => 'John'], '%!@$§', 'John'],
            [['%!@$§.' => 'John'], '[%!@$§.]', 'John'],
            [['index' => ['%!@$§.' => 'John']], 'index[%!@$§.]', 'John'],
            [(object)['%!@$§' => 'John'], '%!@$§', 'John'],
            [(object)['property' => (object)['%!@$§' => 'John']], 'property.%!@$§', 'John'],

            // Nested objects and arrays
            [['foo' => new TestClass('bar')], '[foo].publicGetSetter', 'bar'],
            [new TestClass(['foo' => 'bar']), 'publicGetSetter[foo]', 'bar'],
            [new TestClass(new TestClass('bar')), 'publicGetter.publicGetSetter', 'bar'],
            [
                new TestClass(new TestClass(new TestClass('bar'))),
                'publicGetter.publicGetter.publicGetSetter', 'bar'
            ],
        ];
    }

    public function getValidPropertyPathsWhenIndexExceptionsDisabled(): array
    {
        return array_merge(
            $this->getValidPropertyPaths(),
            [
                // Missing indices
                [['index' => []], '[index][firstName]', null],
                [['root' => ['index' => []]], '[root][index][firstName]', null],

                // Nested objects and arrays
                [new TestClass(['foo' => new TestClass('bar')]), 'publicGetter[foo].publicGetSetter', 'bar'],
                [
                    new TestClass(['foo' => ['baz' => new TestClass('bar')]]),
                    'publicGetter[foo][baz].publicGetSetter', 'bar'
                ],
            ]
        );
    }

    public function getValidPropertyPathsForRemove(): array
    {
        return [
            [['John', 'Doo'], '0'],
            [['John', 'Doo'], '1'],
            [['firstName' => 'John'], 'firstName'],
            [['firstName' => 'John'], '[firstName]'],
            [['index' => ['firstName' => 'John']], 'index.firstName'],
            [['index' => ['firstName' => 'John']], '[index][firstName]'],
            [['index' => ['firstName' => 'John']], '[index].firstName'],
            [['index' => ['firstName' => 'John']], 'index[firstName]'],
            // Accessor methods
            [new TestClass('John'), 'publicAccessor', null],
            [new TestClass('John'), '[publicAccessor]', null],
            [new TestClass('John'), 'publicAccessorWithDefaultValue', null],
            [new TestClass('John'), '[publicAccessorWithDefaultValue]', null],
            // Methods are camelized
            [new TestClass('John'), 'public_accessor', null],
            [new TestClass('John'), '[public_accessor]', null],
            [new TestClass('John'), '_public_accessor', null],
            [new TestClass('John'), '[_public_accessor]', null],
            // Special chars
            [['%!@$§' => 'John'], '%!@$§'],
            [['%!@$§.' => 'John'], '[%!@$§.]'],
            [['index' => ['%!@$§.' => 'John']], 'index[%!@$§.]'],
        ];
    }

    public function testTicket5755(): void
    {
        $object = new Ticket5775Object();

        $this->propertyAccessor->setValue($object, 'property', 'foobar');

        $this->assertEquals('foobar', $object->getProperty());
    }

    public function testSetValueDeepWithMagicGetter(): void
    {
        $obj = new TestClassMagicGet('foo');
        $obj->publicProperty = ['foo' => ['bar' => 'some_value']];
        $this->propertyAccessor->setValue($obj, 'publicProperty[foo][bar]', 'Updated');
        $this->assertSame('Updated', $obj->publicProperty['foo']['bar']);
    }
}
