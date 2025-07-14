<?php

namespace Oro\Bundle\SyncBundle\Tests\Unit\Content;

use Oro\Bundle\SyncBundle\Content\SimpleTagGenerator;
use PHPUnit\Framework\TestCase;

class SimpleTagGeneratorTest extends TestCase
{
    private SimpleTagGenerator $generator;

    #[\Override]
    protected function setUp(): void
    {
        $this->generator = new SimpleTagGenerator();
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(mixed $data, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->generator->supports($data));
    }

    public function supportsDataProvider(): array
    {
        return [
            'simple array given'                          => [['name' => 'tagSimpleName'], true],
            'given array with name and params'            => [['name' => 'tagSimpleName', 'params' => ['das']], true],
            'given array with name and params and nested' => [
                ['name' => 'tagSimpleName', 'params' => ['das'], 'children' => ['some nested data']],
                true
            ],
            'given empty array w/o name'                  => [[], false],
            'given string'                                => ['testString', false],
            'given object'                                => [new \stdClass(), false]
        ];
    }

    /**
     * @dataProvider generateDataProvider
     */
    public function testGenerate(
        mixed $data,
        bool $includeCollectionTag,
        bool $processNestedData,
        int $expectedCount
    ): void {
        $result = $this->generator->generate($data, $includeCollectionTag, $processNestedData);
        $this->assertCount($expectedCount, $result);
    }

    public function generateDataProvider(): array
    {
        return [
            'should return tags by name param'                                  =>
                [['name' => 'testName'], false, false, 1],
            'should return tags by name param and params'                       =>
                [['name' => 'testName', 'params' => ['test']], false, false, 1],
            'should return tags by name param with collection data '            =>
                [['name' => 'testName'], true, false, 2],
            'should return tags by name param and params with collection data ' =>
                [['name' => 'testName', 'params' => ['test']], true, false, 2],
            'should process nested data'                                        =>
                [['name' => 'testName', 'children' => [['name' => 'testName']]], false, true, 2],
            'should process nested data only for one level'                     =>
                [
                    ['name'     => 'testName',
                     'children' => [['name' => 'testName', 'children' => [['name' => 'testName']]]]
                    ],
                    false,
                    true,
                    2
                ],
        ];
    }

    public function testGenerateIncludesParams(): void
    {
        $tagWOParams = ['name' => 'testName'];

        $result = $this->generator->generate($tagWOParams);
        $tagWithParams = $tagWOParams + ['params' => ['activeSection']];
        $this->assertNotEquals(
            $result,
            $this->generator->generate($tagWithParams),
            'Should generate tag depends on given params'
        );
    }
}
