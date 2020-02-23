<?php

declare(strict_types=1);

namespace voku\CssToInlineStyles;

use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ExceptionInterface;
use voku\helper\HtmlDomParser;

/**
 * CSS to Inline Styles class
 */
class CssToInlineStyles
{
    /**
     * regular expression: css media queries
     *
     * @var string
     */
    private static $cssMediaQueriesRegEx = '#(?:____SIMPLE_HTML_DOM__VOKU__AT____|@)media\\s+(?:only\\s)?(?:[\\s{\\(]|screen|all)\\s?[^{]+{.*}\\s*}\\s*#misuU';

    /**
     * regular expression: css charset
     *
     * @var string
     */
    private static $cssCharsetRegEx = '/@charset [\'"][^\'"]+[\'"];/i';

    /**
     * regular expression: conditional inline style tags
     *
     * @var string
     */
    private static $excludeConditionalInlineStylesBlockRegEx = '/<!--\[if.*<style.*-->/isU';

    /**
     * regular expression: inline style tags
     *
     * @var string
     */
    private static $styleTagRegEx = '|<style(?:\s.*)?>(.*)</style>|isuU';

    /**
     * regular expression: html-comments without conditional comments
     *
     * @var string
     */
    private static $htmlCommentWithoutConditionalCommentRegEx = '|<!--(?!\[if).*-->|isU';

    /**
     * regular expression: style-tag with 'cleanup'-css-class
     *
     * @var string
     */
    private static $styleTagWithCleanupClassRegEx = '|<style[^>]+class="cleanup"[^>]*>.*</style>|isU';

    /**
     * regular expression: css-comments
     *
     * @var string
     */
    private static $styleCommentRegEx = '/\\/\\*.*\\*\\//sU';

    /**
     * @var CssSelectorConverter
     */
    private $cssConverter;

    /**
     * The CSS to use.
     *
     * @var string
     */
    private $css;

    /**
     * The CSS-Media-Queries to use.
     *
     * @var string
     */
    private $css_media_queries;

    /**
     * Should the generated HTML be cleaned.
     *
     * @var bool
     */
    private $cleanup = false;

    /**
     * The encoding to use.
     *
     * @var string
     */
    private $encoding = 'UTF-8';

    /**
     * The HTML to process.
     *
     * @var string
     */
    private $html;

    /**
     * Use inline-styles block as CSS.
     *
     * @var bool
     */
    private $useInlineStylesBlock = false;

    /**
     * Use link block reference as CSS.
     *
     * @var bool
     */
    private $loadCSSFromHTML = false;

    /**
     * Strip original style tags.
     *
     * @var bool
     */
    private $stripOriginalStyleTags = false;

    /**
     * Exclude conditional inline-style blocks.
     *
     * @var bool
     */
    private $excludeConditionalInlineStylesBlock = true;

    /**
     * Exclude media queries from "$this->css" and keep media queries for inline-styles blocks.
     *
     * @var bool
     */
    private $excludeMediaQueries = true;

    /**
     * Exclude media queries from "$this->css" and keep media queries for inline-styles blocks.
     *
     * @var bool
     */
    private $excludeCssCharset = true;

    /**
     * Creates an instance, you could set the HTML and CSS here, or load it later.
     *
     * @param string|null $html the HTML to process
     * @param string|null $css  the CSS to use
     */
    public function __construct(string $html = null, string $css = null)
    {
        if ($html !== null) {
            $this->setHTML($html);
        }

        if ($css !== null) {
            $this->setCSS($css);
        }

        if (\class_exists(CssSelectorConverter::class)) {
            $this->cssConverter = new CssSelectorConverter();
        }
    }

    /**
     * Converts the loaded HTML into an HTML-string with inline styles based on the loaded CSS.
     *
     * @param bool        $outputXHTML        [optional] <p>Should we output valid XHTML?</p>
     * @param int|null    $libXMLExtraOptions [optional] <p>$libXMLExtraOptions Since PHP 5.4.0 and Libxml 2.6.0,
     *                                        you may also use the options parameter to specify additional
     *                                        Libxml parameters.
     *                                        </p>
     * @param string|null $path               [optional] <p>Set the path to your external css-files.</p>
     *
     * @throws Exception
     *
     * @return string
     */
    public function convert(bool $outputXHTML = false, int $libXMLExtraOptions = null, $path = null): string
    {
        // validate
        if (!$this->html) {
            throw new Exception('No HTML provided.');
        }

        // use local variables
        $css = $this->css;

        // create new HtmlDomParser
        $dom = HtmlDomParser::str_get_html($this->html, $libXMLExtraOptions);

        // check if there is some link css reference
        if ($this->loadCSSFromHTML) {
            foreach ($dom->findMulti('link') as $node) {
                $file = ($path ?: __DIR__) . '/' . $node->getAttribute('href');

                if (\file_exists($file)) {
                    $css .= \file_get_contents($file);

                    // converting to inline css because we don't need/want to load css files, so remove the link
                    $node->outertext = '';
                }
            }
        }

        // should we use inline style-block
        if ($this->useInlineStylesBlock) {
            if ($this->excludeConditionalInlineStylesBlock === true) {
                $this->html = (string) \preg_replace(self::$excludeConditionalInlineStylesBlockRegEx, '', $this->html);
            }

            $css .= $this->getCssFromInlineHtmlStyleBlock($this->html);
        }

        // process css
        $cssRules = $this->processCSS($css);

        // create new XPath
        $xPath = $this->createXPath($dom->getDocument(), $cssRules);

        // strip original style tags if we need to
        if ($this->stripOriginalStyleTags === true) {
            $this->stripOriginalStyleTags($xPath);
        }

        // cleanup the HTML if we need to
        if ($this->cleanup === true) {
            $this->cleanupHTML($xPath);
        }

        // should we output XHTML?
        if ($outputXHTML === true) {
            return $dom->xml();
        }

        // just regular HTML 4.01 as it should be used in newsletters
        $html = $dom->html();

        // add css media queries from "$this->setCSS()"
        if (
            $this->stripOriginalStyleTags === false
            &&
            $this->css_media_queries
        ) {
            $html = \str_ireplace('</head>', "\n" . '<style type="text/css">' . "\n" . $this->css_media_queries . "\n" . '</style>' . "\n" . '</head>', $html);
        }

        return $html;
    }

    /**
     * get css from inline-html style-block
     *
     * @param string $html
     *
     * @return string
     */
    public function getCssFromInlineHtmlStyleBlock($html): string
    {
        // init var
        $css = '';
        $matches = [];

        $htmlNoComments = (string) \preg_replace(self::$htmlCommentWithoutConditionalCommentRegEx, '', $html);

        // match the style blocks
        \preg_match_all(self::$styleTagRegEx, $htmlNoComments, $matches);

        // any style-blocks found?
        if (!empty($matches[1])) {
            // add
            foreach ($matches[1] as $match) {
                $css .= \trim($match) . "\n";
            }
        }

        return $css;
    }

    /**
     * Set CSS to use.
     *
     * @param string $css <p>The CSS to use.</p>
     *
     * @return $this
     */
    public function setCSS(string $css): self
    {
        $this->css = $css;

        $this->css_media_queries = $this->getMediaQueries($css);

        return $this;
    }

    /**
     * Should the IDs and classes be removed?
     *
     * @param bool $on Should we enable cleanup?
     *
     * @return $this
     */
    public function setCleanup(bool $on = true): self
    {
        $this->cleanup = $on;

        return $this;
    }

    /**
     * Set the encoding to use with the DOMDocument.
     *
     * @param string $encoding the encoding to use
     *
     * @return $this
     *
     * @deprecated Doesn't have any effect
     */
    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Set exclude conditional inline-style blocks.
     *
     * e.g.: <!--[if gte mso 9]><style>.foo { bar } </style><![endif]-->
     *
     * @param bool $on
     *
     * @return $this
     */
    public function setExcludeConditionalInlineStylesBlock(bool $on = true): self
    {
        $this->excludeConditionalInlineStylesBlock = $on;

        return $this;
    }

    /**
     * Set exclude charset.
     *
     * @param bool $on
     *
     * @return $this
     */
    public function setExcludeCssCharset(bool $on = true): self
    {
        $this->excludeCssCharset = $on;

        return $this;
    }

    /**
     * Set exclude media queries.
     *
     * Info: If this is enabled the media queries will be removed before inlining the rules.
     *
     * WARNING: If you use inline styles block "<style>" this option will keep the media queries.
     *
     * @param bool $on
     *
     * @return $this
     */
    public function setExcludeMediaQueries(bool $on = true): self
    {
        $this->excludeMediaQueries = $on;

        return $this;
    }

    /**
     * Set HTML to process.
     *
     * @param string $html <p>The HTML to process.</p>
     *
     * @return $this
     */
    public function setHTML(string $html): self
    {
        // strip style definitions, if we use css-class "cleanup" on a style-element
        $this->html = (string) \preg_replace(self::$styleTagWithCleanupClassRegEx, ' ', $html);

        return $this;
    }

    /**
     * Set use of inline link block.
     *
     * Info: If this is enabled the class will use the links reference in the HTML.
     *
     * @param bool $on [optional] Should we process link styles?
     *
     * @return $this
     */
    public function setLoadCSSFromHTML(bool $on = true): self
    {
        $this->loadCSSFromHTML = $on;

        return $this;
    }

    /**
     * Set strip original style tags.
     *
     * Info: If this is enabled the class will remove all style tags in the HTML.
     *
     * @param bool $on Should we process inline styles?
     *
     * @return $this
     */
    public function setStripOriginalStyleTags(bool $on = true): self
    {
        $this->stripOriginalStyleTags = $on;

        return $this;
    }

    /**
     * Set use of inline styles block.
     *
     * Info: If this is enabled the class will use the style-block in the HTML.
     *
     * @param bool $on Should we process inline styles?
     *
     * @return $this
     */
    public function setUseInlineStylesBlock(bool $on = true): self
    {
        $this->useInlineStylesBlock = $on;

        return $this;
    }

    /**
     * Remove id and class attributes.
     *
     * @param \DOMXPath $xPath the DOMXPath for the entire document
     *
     * @return void
     */
    private function cleanupHTML(\DOMXPath $xPath)
    {
        /** @var \DOMAttr[]|\DOMNodeList<\DOMAttr>|false $nodes */
        $nodes = $xPath->query('//@class | //@id');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $node->ownerElement->removeAttributeNode($node);
            }
        }
    }

    /**
     * @param \DOMElement $element
     * @param array       $ruleProperties
     *
     * @return string
     */
    private function createPropertyChunks(\DOMElement $element, array $ruleProperties): string
    {
        // init var
        $properties = [];

        // get current styles
        /** @var \DOMAttr|null $stylesAttribute */
        $stylesAttribute = $element->attributes->getNamedItem('style');

        // any styles defined before?
        if ($stylesAttribute !== null) {
            // get value for the styles attribute
            /** @noinspection PhpUndefinedFieldInspection */
            $definedStyles = (string) $stylesAttribute->value;

            // split into properties
            $definedProperties = $this->splitIntoProperties($definedStyles);

            $properties = $this->splitStyleIntoChunks($definedProperties);
        }

        // add new properties into the list
        foreach ($ruleProperties as $key => $value) {
            // If one of the rules is already set and is !important, don't apply it,
            // except if the new rule is also important.
            if (
                !isset($properties[$key])
                ||
                \stripos($properties[$key], '!important') === false
                ||
                \stripos(\implode('', (array) $value), '!important') !== false
            ) {
                unset($properties[$key]);
                $properties[$key] = $value;
            }
        }

        // build string
        $propertyChunks = [];

        // build chunks
        foreach ($properties as $key => $values) {
            foreach ((array) $values as $value) {
                $propertyChunks[] = $key . ': ' . $value . ';';
            }
        }

        return \implode(' ', $propertyChunks);
    }

    /**
     * create XPath
     *
     * @param \DOMDocument $document
     * @param array        $cssRules
     *
     * @return \DOMXPath
     */
    private function createXPath(\DOMDocument $document, array $cssRules): \DOMXPath
    {
        /** @var \DOMElement[]|\SplObjectStorage $propertyStorage */
        $propertyStorage = new \SplObjectStorage();
        $xPath = new \DOMXPath($document);

        // any rules?
        if (\count($cssRules) !== 0) {
            // loop rules
            foreach ($cssRules as $rule) {
                $ruleSelector = $rule['selector'];
                $ruleProperties = $rule['properties'];

                if (!$ruleSelector || !$ruleProperties) {
                    continue;
                }

                try {
                    $query = $this->cssConverter->toXPath($ruleSelector);
                } catch (ExceptionInterface $e) {
                    $query = null;
                }

                // validate query
                if ($query === null) {
                    continue;
                }

                // search elements
                /** @var \DOMElement[]|\DOMNodeList<\DOMElement>|false $elements */
                $elements = $xPath->query($query);

                // validate elements
                if ($elements === false) {
                    continue;
                }

                // loop found elements
                foreach ($elements as $element) {
                    if (
                        $ruleSelector === '*'
                        &&
                        (
                            $element->tagName === 'html'
                            || $element->tagName === 'title'
                            || $element->tagName === 'meta'
                            || $element->tagName === 'head'
                            || $element->tagName === 'style'
                            || $element->tagName === 'script'
                            || $element->tagName === 'link'
                        )
                    ) {
                        continue;
                    }

                    // no styles stored?
                    if (!isset($propertyStorage[$element])) {

                        // init var
                        /** @var \DOMAttr|null $originalStyle */
                        $originalStyle = $element->attributes->getNamedItem('style');

                        if ($originalStyle !== null) {
                            $originalStyle = (string) $originalStyle->value;
                        } else {
                            $originalStyle = '';
                        }

                        // store original styles
                        $propertyStorage->attach($element, $originalStyle);

                        // clear the styles
                        $element->setAttribute('style', '');
                    }

                    // set attribute
                    $propertiesString = $this->createPropertyChunks($element, $ruleProperties);
                    if ($propertiesString) {
                        $element->setAttribute('style', $propertiesString);
                    }
                }
            }

            foreach ($propertyStorage as $element) {
                $originalStyle = $propertyStorage->getInfo();
                if ($originalStyle) {
                    $originalStyles = $this->splitIntoProperties($originalStyle);
                    $originalProperties = $this->splitStyleIntoChunks($originalStyles);

                    // set attribute
                    $propertiesString = $this->createPropertyChunks($element, $originalProperties);
                    if ($propertiesString) {
                        $element->setAttribute('style', $propertiesString);
                    }
                }
            }
        }

        return $xPath;
    }

    /**
     * @param string $css
     *
     * @return string
     */
    private function doCleanup($css): string
    {
        // remove newlines & replace double quotes by single quotes
        $css = \str_replace(
            ["\r", "\n", '"'],
            ['', '', '\''],
            $css
        );

        // remove comments
        $css = (string) \preg_replace(self::$styleCommentRegEx, '', $css);

        // remove spaces
        $css = (string) \preg_replace('/\s\s+/u', ' ', $css);

        // remove css charset
        if ($this->excludeCssCharset === true) {
            $css = $this->stripeCharsetInCss($css);
        }

        // remove css media queries
        if ($this->excludeMediaQueries === true) {
            $css = $this->stripeMediaQueries($css);
        }

        return (string) $css;
    }

    /**
     * get css media queries from the string
     *
     * @param string $css
     *
     * @return string
     */
    private function getMediaQueries($css): string
    {
        // remove comments previously to matching media queries
        $css = (string) \preg_replace(self::$styleCommentRegEx, '', $css);

        \preg_match_all(self::$cssMediaQueriesRegEx, $css, $matches);

        return \implode("\n", $matches[0]);
    }

    /**
     * Process the loaded CSS
     *
     * @param string $css
     *
     * @return array
     */
    private function processCSS($css): array
    {
        //reset current set of rules
        $cssRules = [];

        // init vars
        $css = (string) $css;

        $css = $this->doCleanup($css);

        // rules are splitted by }
        $rules = \explode('}', $css);

        // init var
        $i = 1;

        // loop rules
        foreach ($rules as $rule) {
            // split into chunks
            $chunks = \explode('{', $rule);

            // invalid rule?
            if (!isset($chunks[1])) {
                continue;
            }

            // set the selectors
            $selectors = \trim($chunks[0]);

            // get css-properties
            $cssProperties = \trim($chunks[1]);

            // split multiple selectors
            $selectors = \explode(',', $selectors);

            // loop selectors
            foreach ($selectors as $selector) {
                // cleanup
                $selector = \trim($selector);

                // build an array for each selector
                $ruleSet = [];

                // store selector
                $ruleSet['selector'] = $selector;

                // process the properties
                $ruleSet['properties'] = $this->processCSSProperties($cssProperties);

                // calculate specificity
                $ruleSet['specificity'] = Specificity::fromSelector($selector);

                // remember the order in which the rules appear
                $ruleSet['order'] = $i;

                // add into rules
                $cssRules[] = $ruleSet;

                // increment
                ++$i;
            }
        }

        // sort based on specificity
        if (\count($cssRules) !== 0) {
            \usort($cssRules, [__CLASS__, 'sortOnSpecificity']);
        }

        return $cssRules;
    }

    /**
     * Process the CSS-properties
     *
     * @param string $propertyString the CSS-properties
     *
     * @return array
     */
    private function processCSSProperties($propertyString): array
    {
        // split into chunks
        $properties = $this->splitIntoProperties($propertyString);

        // init var
        $pairs = [];

        // loop properties
        foreach ($properties as $property) {
            // split into chunks
            $chunks = \explode(':', $property, 2);

            // validate
            if (!isset($chunks[1])) {
                continue;
            }

            // cleanup
            $chunks[0] = \trim($chunks[0]);
            $chunks[1] = \trim($chunks[1]);

            // add to pairs array
            if (
                !isset($pairs[$chunks[0]])
                ||
                !\in_array($chunks[1], $pairs[$chunks[0]], true)
            ) {
                $pairs[$chunks[0]][] = $chunks[1];
            }
        }

        // sort the pairs
        \ksort($pairs);

        // return
        return $pairs;
    }

    /**
     * Sort an array on the specificity element in an ascending way.
     *
     * INFO: Lower specificity will be sorted to the beginning of the array.
     *
     * @param array $e1 the first element
     * @param array $e2 the second element
     *
     * @return int
     *
     * @psalm-param array<specificity: Specificity, order: int> $e1
     * @psalm-param array<specificity: Specificity, order: int> $e2
     */
    private static function sortOnSpecificity(array $e1, array $e2): int
    {
        // Compare the specificity
        $value = $e1['specificity']->compareTo($e2['specificity']);

        // if the specificity is the same, use the order in which the element appeared
        if ($value === 0) {
            $value = $e1['order'] - $e2['order'];
        }

        return $value;
    }

    /**
     * Split a style string into an array of properties.
     * The returned array can contain empty strings.
     *
     * @param string $styles ex: 'color:blue;font-size:12px;'
     *
     * @return array an array of strings containing css property ex: array('color:blue','font-size:12px')
     */
    private function splitIntoProperties($styles): array
    {
        $properties = \explode(';', $styles);
        $propertiesCount = \count($properties);

        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i < $propertiesCount; ++$i) {
            // If next property begins with base64,
            // Then the ';' was part of this property (and we should not have split on it).
            if (
                isset($properties[$i + 1])
                &&
                \strpos($properties[$i + 1], 'base64,') !== false
            ) {
                $properties[$i] .= ';' . $properties[$i + 1];
                $properties[$i + 1] = '';
                ++$i;
            }
        }

        return $properties;
    }

    /**
     * @param array $definedProperties
     *
     * @return array
     */
    private function splitStyleIntoChunks(array $definedProperties): array
    {
        // init var
        $properties = [];

        // loop properties
        foreach ($definedProperties as $property) {
            // validate property
            if (
                !$property
                ||
                \strpos($property, ':') === false
            ) {
                continue;
            }

            // split into chunks
            $chunks = \explode(':', \trim($property), 2);

            // validate
            if (!isset($chunks[1])) {
                continue;
            }

            // loop chunks
            $properties[$chunks[0]] = \trim($chunks[1]);
        }

        return $properties;
    }

    /**
     * Strip style tags into the generated HTML.
     *
     * @param \DOMXPath $xPath the DOMXPath for the entire document
     *
     * @return void
     */
    private function stripOriginalStyleTags(\DOMXPath $xPath)
    {
        // get all style tags
        /** @var \DOMElement[]|\DOMNodeList<\DOMElement>|false $nodes */
        $nodes = $xPath->query('descendant-or-self::style');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($this->excludeMediaQueries === true) {

                    // remove comments previously to matching media queries
                    $nodeValueTmp = (string) \preg_replace(self::$styleCommentRegEx, '', $node->nodeValue);

                    // search for Media Queries
                    \preg_match_all(self::$cssMediaQueriesRegEx, $nodeValueTmp, $mqs);

                    // replace the nodeValue with just the Media Queries
                    $node->nodeValue = \implode("\n", $mqs[0]);
                } else {
                    // remove the entire style tag
                    if ($node->parentNode !== null) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }
    }

    /**
     * remove charset from the string
     *
     * @param string $css
     *
     * @return string
     */
    private function stripeCharsetInCss($css): string
    {
        return (string) \preg_replace(self::$cssCharsetRegEx, '', $css);
    }

    /**
     * remove css media queries from the string
     *
     * @param string $css
     *
     * @return string
     */
    private function stripeMediaQueries($css): string
    {
        // remove comments previously to matching media queries
        $css = (string) \preg_replace(self::$styleCommentRegEx, '', $css);

        return (string) \preg_replace(self::$cssMediaQueriesRegEx, '', $css);
    }
}
