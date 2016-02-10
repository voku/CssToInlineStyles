<?php
namespace voku\CssToInlineStyles;

use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ExceptionInterface;

/**
 * CSS to Inline Styles class
 *
 * @author     Tijs Verkoyen <php-css-to-inline-styles@verkoyen.eu>
 */
class CssToInlineStyles
{

  /**
   * regular expression: css media queries
   *
   * @var string
   */
  private static $cssMediaQueriesRegEx = '#@media\\s+(?:only\\s)?(?:[\\s{\\(]|screen|all)\\s?[^{]+{.*}\\s*}\\s*#misU';

  /**
   * regular expression: conditional inline style tags
   *
   * @var string
   */
  private static $excludeConditionalInlineStylesBlockRegEx = '/<!--.*<style.*?-->/is';

  /**
   * regular expression: inline style tags
   *
   * @var string
   */
  private static $styleTagRegEx = '|<style(.*)>(.*)</style>|isU';

  /**
   * regular expression: css-comments
   *
   * @var string
   */
  private static $styleCommentRegEx = '/\\/\\*.*\\*\\//sU';

  /**
   * The CSS to use
   *
   * @var  string
   */
  private $css;

  /**
   * Should the generated HTML be cleaned
   *
   * @var  bool
   */
  private $cleanup = false;

  /**
   * The encoding to use.
   *
   * @var  string
   */
  private $encoding = 'UTF-8';

  /**
   * The HTML to process
   *
   * @var  string
   */
  private $html;

  /**
   * Use inline-styles block as CSS
   *
   * @var bool
   */
  private $useInlineStylesBlock = false;

  /**
   * Use link block reference as CSS
   *
   * @var bool
   */
  private $loadCSSFromHTML = false;

  /**
   * Strip original style tags
   *
   * @var bool
   */
  private $stripOriginalStyleTags = false;

  /**
   * Exclude conditional inline-style blocks
   *
   * @var bool
   */
  private $excludeConditionalInlineStylesBlock = true;

  /**
   * Exclude media queries from "$this->css" and keep media queries for inline-styles blocks
   *
   * @var bool
   */
  private $excludeMediaQueries = true;

  /**
   * Creates an instance, you could set the HTML and CSS here, or load it
   * later.
   *
   * @param  null|string $html The HTML to process.
   * @param  null|string $css  The CSS to use.
   */
  public function __construct($html = null, $css = null)
  {
    if (null !== $html) {
      $this->setHTML($html);
    }

    if (null !== $css) {
      $this->setCSS($css);
    }
  }

  /**
   * Set HTML to process
   *
   * @param  string $html The HTML to process.
   */
  public function setHTML($html)
  {
    // strip style definitions, if we use css-class "cleanup" on a style-element
    $this->html = (string)preg_replace('/<style[^>]+class="cleanup"[^>]*>.*<\/style>/Usi', ' ', $html);
  }

  /**
   * Set CSS to use
   *
   * @param  string $css The CSS to use.
   */
  public function setCSS($css)
  {
    $this->css = (string)$css;
  }

  /**
   * Sort an array on the specificity element
   *
   * @return int
   *
   * @param Specificity[] $e1 The first element.
   * @param Specificity[] $e2 The second element.
   */
  private static function sortOnSpecificity($e1, $e2)
  {
    // Compare the specificity
    $value = $e1['specificity']->compareTo($e2['specificity']);

    // if the specificity is the same, use the order in which the element appeared
    if (0 === $value) {
      $value = $e1['order'] - $e2['order'];
    }

    return $value;
  }

  /**
   * Converts the loaded HTML into an HTML-string with inline styles based on the loaded CSS
   *
   * @return string
   *
   * @param  bool $outputXHTML                 [optional] Should we output valid XHTML?
   * @param  int  $libXMLOptions               [optional] $libXMLOptions Since PHP 5.4.0 and Libxml 2.6.0, you may also
   *                                                      use the options parameter to specify additional Libxml
   *                                                      parameters. Recommend these options: LIBXML_HTML_NOIMPLIED |
   *                                                      LIBXML_HTML_NODEFDTD
   * @param false|string                       [optional] Set the path to your external css-files.
   *
   * @throws Exception
   */
  public function convert($outputXHTML = false, $libXMLOptions = 0, $path = false)
  {
    // redefine
    $outputXHTML = (bool)$outputXHTML;

    // validate
    if (!$this->html) {
      throw new Exception('No HTML provided.');
    }

    // use local variables
    $css = $this->css;

    // create new DOMDocument
    $document = $this->createDOMDocument($this->html, $libXMLOptions);

    // check if there is some link css reference
    if ($this->loadCSSFromHTML) {
      foreach ($document->getElementsByTagName('link') as $node) {
        $file = ($path ? $path : __DIR__) . '/' .  $node->getAttribute('href');

        if (file_exists($file)) {
          $css .= file_get_contents($file);

          // converting to inline css because we don't need/want to load css files, so remove the link
          $node->parentNode->removeChild($node);
        }
      }
    }

    // should we use inline style-block
    if ($this->useInlineStylesBlock) {

      if (true === $this->excludeConditionalInlineStylesBlock) {
        $this->html = preg_replace(self::$excludeConditionalInlineStylesBlockRegEx, '', $this->html);
      }

      $css .= $this->getCssFromInlineHtmlStyleBlock($this->html);
    }

    // process css
    $cssRules = $this->processCSS($css);

    // create new XPath
    $xPath = $this->createXPath($document, $cssRules);

    // strip original style tags if we need to
    if ($this->stripOriginalStyleTags === true) {
      $this->stripOriginalStyleTags($xPath);
    }

    // cleanup the HTML if we need to
    if (true === $this->cleanup) {
      $this->cleanupHTML($xPath);
    }

    // should we output XHTML?
    if (true === $outputXHTML) {
      // set formatting
      $document->formatOutput = true;

      // get the HTML as XML
      $html = $document->saveXML(null, LIBXML_NOEMPTYTAG);

      // remove the XML-header
      return ltrim(preg_replace('/<\?xml.*\?>/', '', $html));
    }

    // just regular HTML 4.01 as it should be used in newsletters
    return $document->saveHTML();
  }

  /**
   * get css from inline-html style-block
   *
   * @param string $html
   *
   * @return string
   */
  public function getCssFromInlineHtmlStyleBlock($html)
  {
    // init var
    $css = '';
    $matches = array();

    // match the style blocks
    preg_match_all(self::$styleTagRegEx, $html, $matches);

    // any style-blocks found?
    if (!empty($matches[2])) {
      // add
      foreach ($matches[2] as $match) {
        $css .= trim($match) . "\n";
      }
    }

    return $css;
  }

  /**
   * Process the loaded CSS
   *
   * @param $css
   *
   * @return array
   */
  private function processCSS($css)
  {
    //reset current set of rules
    $cssRules = array();

    // init vars
    $css = (string)$css;

    $css = $this->doCleanup($css);

    // rules are splitted by }
    $rules = (array)explode('}', $css);

    // init var
    $i = 1;

    // loop rules
    foreach ($rules as $rule) {
      // split into chunks
      $chunks = explode('{', $rule);

      // invalid rule?
      if (!isset($chunks[1])) {
        continue;
      }

      // set the selectors
      $selectors = trim($chunks[0]);

      // get cssProperties
      $cssProperties = trim($chunks[1]);

      // split multiple selectors
      $selectors = (array)explode(',', $selectors);

      // loop selectors
      foreach ($selectors as $selector) {
        // cleanup
        $selector = trim($selector);

        // build an array for each selector
        $ruleSet = array();

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
        $i++;
      }
    }

    // sort based on specificity
    if (0 !== count($cssRules)) {
      usort($cssRules, array(__CLASS__, 'sortOnSpecificity'));
    }

    return $cssRules;
  }

  /**
   * @param string $css
   *
   * @return string
   */
  private function doCleanup($css)
  {
    // remove newlines & replace double quotes by single quotes
    $css = str_replace(
        array("\r", "\n", '"'),
        array('', '', '\''),
        $css
    );

    // remove comments
    $css = preg_replace(self::$styleCommentRegEx, '', $css);

    // remove spaces
    $css = preg_replace('/\s\s+/', ' ', $css);

    // remove css media queries
    if (true === $this->excludeMediaQueries) {
      $css = $this->stripeMediaQueries($css);
    }

    return (string)$css;
  }

  /**
   * remove css media queries from the string
   *
   * @param string $css
   *
   * @return string
   */
  private function stripeMediaQueries($css)
  {
    // remove comments previously to matching media queries
    $css = preg_replace(self::$styleCommentRegEx, '', $css);

    return (string)preg_replace(self::$cssMediaQueriesRegEx, '', $css);
  }

  /**
   * Process the CSS-properties
   *
   * @return array
   *
   * @param  string $propertyString The CSS-properties.
   */
  private function processCSSProperties($propertyString)
  {
    // split into chunks
    $properties = $this->splitIntoProperties($propertyString);

    // init var
    $pairs = array();

    // loop properties
    foreach ($properties as $property) {
      // split into chunks
      $chunks = (array)explode(':', $property, 2);

      // validate
      if (!isset($chunks[1])) {
        continue;
      }

      // cleanup
      $chunks[0] = trim($chunks[0]);
      $chunks[1] = trim($chunks[1]);

      // add to pairs array
      if (
          !isset($pairs[$chunks[0]])
          ||
          !in_array($chunks[1], $pairs[$chunks[0]], true)
      ) {
        $pairs[$chunks[0]][] = $chunks[1];
      }
    }

    // sort the pairs
    ksort($pairs);

    // return
    return $pairs;
  }

  /**
   * Split a style string into an array of properties.
   * The returned array can contain empty strings.
   *
   * @param string $styles ex: 'color:blue;font-size:12px;'
   *
   * @return array an array of strings containing css property ex: array('color:blue','font-size:12px')
   */
  private function splitIntoProperties($styles)
  {
    $properties = (array)explode(';', $styles);
    $propertiesCount = count($properties);

    for ($i = 0; $i < $propertiesCount; $i++) {
      // If next property begins with base64,
      // Then the ';' was part of this property (and we should not have split on it).
      if (
          isset($properties[$i + 1])
          &&
          strpos($properties[$i + 1], 'base64,') !== false
      ) {
        $properties[$i] .= ';' . $properties[$i + 1];
        $properties[$i + 1] = '';
        ++$i;
      }
    }

    return $properties;
  }

  /**
   * create DOMDocument from HTML
   *
   * @param string $html
   * @param int    $libXMLOptions
   *
   * @return \DOMDocument
   */
  private function createDOMDocument($html, $libXMLOptions = 0)
  {
    // create new DOMDocument
    $document = new \DOMDocument('1.0', $this->getEncoding());

    // DOMDocument settings
    $document->preserveWhiteSpace = false;
    $document->formatOutput = true;

    // set error level
    $internalErrors = libxml_use_internal_errors(true);

    // load HTML
    //
    // with UTF-8 hack: http://php.net/manual/en/domdocument.loadhtml.php#95251
    //
    if ($libXMLOptions !== 0) {
      $document->loadHTML('<?xml encoding="' . $this->getEncoding() . '">' . $html, $libXMLOptions);
    } else {
      $document->loadHTML('<?xml encoding="' . $this->getEncoding() . '">' . $html);
    }


    // remove the "xml-encoding" hack
    foreach ($document->childNodes as $child) {
      if ($child->nodeType == XML_PI_NODE) {
        $document->removeChild($child);
      }
    }

    // set encoding
    $document->encoding = $this->getEncoding();

    // restore error level
    libxml_use_internal_errors($internalErrors);

    return $document;
  }

  /**
   * Get the encoding to use
   *
   * @return string
   */
  private function getEncoding()
  {
    return $this->encoding;
  }

  /**
   * create XPath
   *
   * @param \DOMDocument $document
   * @param array        $cssRules
   *
   * @return \DOMXPath
   */
  private function createXPath(\DOMDocument $document, array $cssRules)
  {
    $xPath = new \DOMXPath($document);

    // any rules?
    if (0 !== count($cssRules)) {
      // loop rules
      foreach ($cssRules as $rule) {

        try {
          $converter = new CssSelectorConverter();
          $query = $converter->toXPath($rule['selector']);
        } catch (ExceptionInterface $e) {
          $query = null;
        }
        $converter = null;

        // validate query
        if (null === $query) {
          continue;
        }

        // search elements
        $elements = $xPath->query($query);

        // validate elements
        if (false === $elements) {
          continue;
        }

        // loop found elements
        foreach ($elements as $element) {

          /**
           * @var $element \DOMElement
           */

          // no styles stored?
          if (null === $element->attributes->getNamedItem('data-css-to-inline-styles-original-styles')) {

            // init var
            $originalStyle = '';

            if (null !== $element->attributes->getNamedItem('style')) {
              $originalStyle = $element->attributes->getNamedItem('style')->value;
            }

            // store original styles
            $element->setAttribute('data-css-to-inline-styles-original-styles', $originalStyle);

            // clear the styles
            $element->setAttribute('style', '');
          }

          $propertiesString = $this->createPropertyChunks($element, $rule['properties']);

          // set attribute
          if ('' != $propertiesString) {
            $element->setAttribute('style', $propertiesString);
          }
        }
      }

      // reapply original styles
      // search elements
      $elements = $xPath->query('//*[@data-css-to-inline-styles-original-styles]');

      // loop found elements
      foreach ($elements as $element) {
        // get the original styles
        $originalStyle = $element->attributes->getNamedItem('data-css-to-inline-styles-original-styles')->value;

        if ('' != $originalStyle) {
          $originalStyles = $this->splitIntoProperties($originalStyle);

          $originalProperties = $this->splitStyleIntoChunks($originalStyles);

          $propertiesString = $this->createPropertyChunks($element, $originalProperties);

          // set attribute
          if ('' != $propertiesString) {
            $element->setAttribute('style', $propertiesString);
          }
        }

        // remove placeholder
        $element->removeAttribute('data-css-to-inline-styles-original-styles');
      }
    }

    return $xPath;
  }

  /**
   * @param \DOMElement $element
   * @param array       $ruleProperties
   *
   * @return array
   */
  private function createPropertyChunks(\DOMElement $element, array $ruleProperties)
  {
    // init var
    $properties = array();

    // get current styles
    $stylesAttribute = $element->attributes->getNamedItem('style');

    // any styles defined before?
    if (null !== $stylesAttribute) {
      // get value for the styles attribute
      $definedStyles = (string)$stylesAttribute->value;

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
          false === stripos($properties[$key], '!important')
          ||
          false !== stripos(implode('', (array)$value), '!important')
      ) {
        $properties[$key] = $value;
      }
    }

    // build string
    $propertyChunks = array();

    // build chunks
    foreach ($properties as $key => $values) {
      foreach ((array)$values as $value) {
        $propertyChunks[] = $key . ': ' . $value . ';';
      }
    }

    return implode(' ', $propertyChunks);
  }

  /**
   * @param array $definedProperties
   *
   * @return array
   */
  private function splitStyleIntoChunks(array $definedProperties)
  {
    // init var
    $properties = array();

    // loop properties
    foreach ($definedProperties as $property) {
      // validate property
      if (
          !$property
          ||
          strpos($property, ':') === false
      ) {
        continue;
      }

      // split into chunks
      $chunks = (array)explode(':', trim($property), 2);

      // validate
      if (!isset($chunks[1])) {
        continue;
      }

      // loop chunks
      $properties[$chunks[0]] = trim($chunks[1]);
    }

    return $properties;
  }

  /**
   * Strip style tags into the generated HTML
   *
   * @param  \DOMXPath $xPath The DOMXPath for the entire document.
   *
   * @return string
   */
  private function stripOriginalStyleTags(\DOMXPath $xPath)
  {
    // get all style tags
    $nodes = $xPath->query('descendant-or-self::style');
    foreach ($nodes as $node) {
      if ($this->excludeMediaQueries === true) {

        // remove comments previously to matching media queries
        $node->nodeValue = preg_replace(self::$styleCommentRegEx, '', $node->nodeValue);

        // search for Media Queries
        preg_match_all(self::$cssMediaQueriesRegEx, $node->nodeValue, $mqs);

        // replace the nodeValue with just the Media Queries
        $node->nodeValue = implode("\n", $mqs[0]);

      } else {
        // remove the entire style tag
        $node->parentNode->removeChild($node);
      }
    }
  }

  /**
   * Remove id and class attributes.
   *
   * @param  \DOMXPath $xPath The DOMXPath for the entire document.
   *
   * @return string
   */
  private function cleanupHTML(\DOMXPath $xPath)
  {
    $nodes = $xPath->query('//@class | //@id');
    foreach ($nodes as $node) {
      $node->ownerElement->removeAttributeNode($node);
    }
  }

  /**
   * Should the IDs and classes be removed?
   *
   * @param  bool $on Should we enable cleanup?
   */
  public function setCleanup($on = true)
  {
    $this->cleanup = (bool)$on;
  }

  /**
   * Set the encoding to use with the DOMDocument
   *
   * @param  string $encoding The encoding to use.
   *
   * @deprecated Doesn't have any effect
   */
  public function setEncoding($encoding)
  {
    $this->encoding = (string)$encoding;
  }

  /**
   * Set use of inline styles block
   * If this is enabled the class will use the style-block in the HTML.
   *
   * @param  bool $on Should we process inline styles?
   */
  public function setUseInlineStylesBlock($on = true)
  {
    $this->useInlineStylesBlock = (bool)$on;
  }

  /**
       * Set use of inline link block
       * If this is enabled the class will use the links reference in the HTML.
       *
       * @return void
       * @param  bool [optional] $on Should we process link styles?
       */
    public function setLoadCSSFromHTML($on = true)
     {
         $this->loadCSSFromHTML = (bool) $on;
     }

  /**
   * Set strip original style tags
   * If this is enabled the class will remove all style tags in the HTML.
   *
   * @param  bool $on Should we process inline styles?
   */
  public function setStripOriginalStyleTags($on = true)
  {
    $this->stripOriginalStyleTags = (bool)$on;
  }

  /**
   * Set exclude media queries
   *
   * If this is enabled the media queries will be removed before inlining the rules.
   *
   * WARNING: If you use inline styles block "<style>" the this option will keep the media queries.
   *
   * @param bool $on
   */
  public function setExcludeMediaQueries($on = true)
  {
    $this->excludeMediaQueries = (bool)$on;
  }

  /**
   * Set exclude conditional inline-style blocks e.g.: <!--[if gte mso 9]><style>.foo { bar } </style><![endif]-->
   *
   * @param bool $on
   */
  public function setExcludeConditionalInlineStylesBlock($on = true)
  {
    $this->excludeConditionalInlineStylesBlock = (bool)$on;
  }

}
