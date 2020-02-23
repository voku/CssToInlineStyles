<?php

declare(strict_types=1);

namespace voku\CssToInlineStyles;

/**
 * CSS to Inline Styles Specificity class.
 *
 * Compare specificity based on the CSS3 spec.
 *
 * @see http://www.w3.org/TR/selectors/#specificity
 */
class Specificity
{
    /**
     * The number of ID selectors in the selector
     *
     * @var int
     */
    private $a;

    /**
     * The number of class selectors, attributes selectors, and pseudo-classes in the selector
     *
     * @var int
     */
    private $b;

    /**
     * The number of type selectors and pseudo-elements in the selector
     *
     * @var int
     */
    private $c;

    /**
     * @param int $a The number of ID selectors in the selector
     * @param int $b The number of class selectors, attributes selectors, and pseudo-classes in the selector
     * @param int $c The number of type selectors and pseudo-elements in the selector
     */
    public function __construct($a = 0, $b = 0, $c = 0)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    /**
     * Increase the current specificity by adding the three values
     *
     * @param int $a The number of ID selectors in the selector
     * @param int $b The number of class selectors, attributes selectors, and pseudo-classes in the selector
     * @param int $c The number of type selectors and pseudo-elements in the selector
     *
     * @return void
     */
    public function increase($a, $b, $c)
    {
        $this->a += $a;
        $this->b += $b;
        $this->c += $c;
    }

    /**
     * Get the specificity values as an array
     *
     * @return array
     */
    public function getValues(): array
    {
        return [$this->a, $this->b, $this->c];
    }

    /**
     * Calculate the specificity based on a CSS Selector string,
     * Based on the patterns from premailer/css_parser by Alex Dunae
     *
     * @see https://github.com/premailer/css_parser/blob/master/lib/css_parser/regexps.rb
     *
     * @param string $selector
     *
     * @return static
     */
    public static function fromSelector(string $selector)
    {
        $pattern_a = "  \#";
        $pattern_b = "  (\.[\w]+)                     # classes
                        |
                        \[(\w+)                       # attributes
                        |
                        (\:(                          # pseudo classes
                          link|visited|active
                          |hover|focus
                          |lang
                          |target
                          |enabled|disabled|checked|indeterminate
                          |root
                          |nth-child|nth-last-child|nth-of-type|nth-last-of-type
                          |first-child|last-child|first-of-type|last-of-type
                          |only-child|only-of-type
                          |empty|contains
                        ))";

        $pattern_c = "  ((^|[\s\+\>\~]+)[\w]+       # elements
                        |
                        \:{1,2}(                    # pseudo-elements
                          after|before
                          |first-letter|first-line
                          |selection
                        )
                      )";

        return new static(
            (int)\preg_match_all("/{$pattern_a}/ix", $selector, $matches),
            (int)\preg_match_all("/{$pattern_b}/ix", $selector, $matches),
            (int)\preg_match_all("/{$pattern_c}/ix", $selector, $matches)
        );
    }

    /**
     * Returns <0 when $specificity is greater, 0 when equal, >0 when smaller
     *
     * @param Specificity $specificity
     *
     * @return int
     */
    public function compareTo(self $specificity): int
    {
        if ($this->a !== $specificity->a) {
            return $this->a - $specificity->a;
        }

        if ($this->b !== $specificity->b) {
            return $this->b - $specificity->b;
        }

        return $this->c - $specificity->c;
    }
}
