<?php

namespace voku\CssToInlineStyles\tests;

use \voku\CssToInlineStyles\Specificity;

/**
 * Class SpecificityTest
 *
 * @package voku\CssToInlineStyles\tests
 */
class SpecificityTest extends \PHPUnit_Framework_TestCase
{
  public function testGetValues()
  {
    $specificity = new Specificity(1, 2, 3);
    self::assertSame(array(1, 2, 3), $specificity->getValues());
  }

  public function testIncreaseValue()
  {
    $specificity = new Specificity(1, 2, 3);
    $specificity->increase(1, 2, 3);
    self::assertSame(array(2, 4, 6), $specificity->getValues());
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
    self::assertSame($result, $a->compareTo($b));
  }

  /**
   * @return array
   */
  public function getCompareTestData()
  {
    return array(
        array(new Specificity(0, 0, 0), new Specificity(0, 0, 0), 0),
        array(new Specificity(0, 0, 1), new Specificity(0, 0, 1), 0),
        array(new Specificity(0, 0, 2), new Specificity(0, 0, 1), 1),
        array(new Specificity(0, 0, 2), new Specificity(0, 0, 3), -1),
        array(new Specificity(0, 4, 0), new Specificity(0, 4, 0), 0),
        array(new Specificity(0, 6, 0), new Specificity(0, 5, 11), 1),
        array(new Specificity(0, 7, 0), new Specificity(0, 8, 0), -1),
        array(new Specificity(9, 0, 0), new Specificity(9, 0, 0), 0),
        array(new Specificity(11, 0, 0), new Specificity(10, 11, 0), 1),
        array(new Specificity(12, 11, 0), new Specificity(13, 0, 0), -1),
    );
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
    self::assertSame($result, $specificity->getValues());
  }

  /**
   * @return array
   */
  public function getSelectorData()
  {
    return array(
        array('*', array(0, 0, 0)),
        array('li', array(0, 0, 1)),
        array('ul li', array(0, 0, 2)),
        array('ul ol+li', array(0, 0, 3)),
        array('h1 + *[rel=up]', array(0, 1, 1)),
        array('ul ol li.red', array(0, 1, 3)),
        array('li.red.level', array(0, 2, 1)),
        array('#x34y', array(1, 0, 0)),
    );
  }

  /**
   * @param $selector
   * @param $result
   *
   * @dataProvider getSkippedSelectorData
   */
  public function testSkippedFromSelector($selector, $result)
  {
    self::markTestSkipped(
        'Skipping edge cases in CSS'
    );

    $specificity = Specificity::fromSelector($selector);
    self::assertSame($result, $specificity->getValues());
  }

  /**
   * @return array
   */
  public function getSkippedSelectorData()
  {
    return array(
        array('#s12:not(FOO)', array(1, 0, 1)),
    );
  }
}
