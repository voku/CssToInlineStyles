<?php

declare(strict_types=1);

namespace voku\CssToInlineStyles\tests;

use voku\CssToInlineStyles\Specificity;

/**
 * Class SpecificityTest
 *
 * @internal
 */
final class SpecificityTest extends \PHPUnit\Framework\TestCase
{
    public function testGetValues()
    {
        $specificity = new Specificity(1, 2, 3);
        static::assertSame([1, 2, 3], $specificity->getValues());
    }

    public function testIncreaseValue()
    {
        $specificity = new Specificity(1, 2, 3);
        $specificity->increase(1, 2, 3);
        static::assertSame([2, 4, 6], $specificity->getValues());
    }

    /**
     * @param Specificity $a
     * @param Specificity $b
     * @param             $result
     *
     * @dataProvider getCompareTestData
     */
    public function testCompare(Specificity $a, Specificity $b, $result)
    {
        static::assertSame($result, $a->compareTo($b));
    }

    /**
     * @return array
     */
    public function getCompareTestData()
    {
        return [
            [new Specificity(0, 0, 0), new Specificity(0, 0, 0), 0],
            [new Specificity(0, 0, 1), new Specificity(0, 0, 1), 0],
            [new Specificity(0, 0, 2), new Specificity(0, 0, 1), 1],
            [new Specificity(0, 0, 2), new Specificity(0, 0, 3), -1],
            [new Specificity(0, 4, 0), new Specificity(0, 4, 0), 0],
            [new Specificity(0, 6, 0), new Specificity(0, 5, 11), 1],
            [new Specificity(0, 7, 0), new Specificity(0, 8, 0), -1],
            [new Specificity(9, 0, 0), new Specificity(9, 0, 0), 0],
            [new Specificity(11, 0, 0), new Specificity(10, 11, 0), 1],
            [new Specificity(12, 11, 0), new Specificity(13, 0, 0), -1],
        ];
    }

    /**
     * @param $selector
     * @param $result
     *
     * @dataProvider getSelectorData
     */
    public function testFromSelector($selector, $result)
    {
        $specificity = Specificity::fromSelector($selector);
        static::assertSame($result, $specificity->getValues());
    }

    /**
     * @return array
     */
    public function getSelectorData()
    {
        return [
            ['*', [0, 0, 0]],
            ['li', [0, 0, 1]],
            ['ul li', [0, 0, 2]],
            ['ul ol+li', [0, 0, 3]],
            ['h1 + *[rel=up]', [0, 1, 1]],
            ['ul ol li.red', [0, 1, 3]],
            ['li.red.level', [0, 2, 1]],
            ['#x34y', [1, 0, 0]],
        ];
    }

    /**
     * @param $selector
     * @param $result
     *
     * @dataProvider getSkippedSelectorData
     */
    public function testSkippedFromSelector($selector, $result)
    {
        static::markTestSkipped(
            'Skipping edge cases in CSS'
        );

        $specificity = Specificity::fromSelector($selector);
        static::assertSame($result, $specificity->getValues());
    }

    /**
     * @return array
     */
    public function getSkippedSelectorData()
    {
        return [
            ['#s12:not(FOO)', [1, 0, 1]],
        ];
    }
}
