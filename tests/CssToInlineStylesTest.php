<?php

namespace voku\CssToInlineStyles\tests;

use voku\CssToInlineStyles\CssToInlineStyles;
use voku\CssToInlineStyles\Exception;
use voku\helper\UTF8;

/**
 * Class CssToInlineStylesTest
 *
 * @package voku\CssToInlineStyles\tests
 */
class CssToInlineStylesTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CssToInlineStyles
   */
  protected $cssToInlineStyles;

  public function setUp()
  {
    $this->cssToInlineStyles = new CssToInlineStyles();
  }

  public function teardown()
  {
    $this->cssToInlineStyles = null;
  }

  public function testSimpleElementSelector()
  {
    $html = '<div></div>';
    $css = 'div { display: none; }';
    $expected = '<div style="display: none;"></div>';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testSimpleElementSelectorV2()
  {
    $html = '<h1></h1><div mc:edit="body_content" style="display: block;"><span>test</span></div>';
    $css = 'div { display: none; }';
    $expected = '<h1></h1><div mc:edit="body_content" style="display: block;"><span>test</span></div>';
    $this->runHTMLToCSS($html, $css, $expected);

    $html = '<h1></h1><div mc:edit="body_content" style=""><span>test</span></div>';
    $css = 'div { display: none; }';
    $expected = '<h1></h1><div mc:edit="body_content" style="display: none;"><span>test</span></div>';
    $this->runHTMLToCSS($html, $css, $expected);

    $html = '<p class="one"></p>';
    $css = <<<EOF
p {
  margin: 0;
}
p {
  margin-bottom: 10px;
}
p {
  margin: 0;
}
p.one {
  padding-bottom: 10px;
}
p {
  padding: 10px;
}
EOF;
    $expected = '<p class="one" style="margin-bottom: 10px; margin: 0; padding: 10px; padding-bottom: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);

    // ---

    $css = <<<EOF
p {
  margin: 0;
}
p {
  margin-bottom: 10px;
}
p {
  margin: 0;
}
p {
  padding-bottom: 10px;
}
p {
  padding: 10px;
}
EOF;
    $expected = '<p class="one" style="margin-bottom: 10px; margin: 0; padding-bottom: 10px; padding: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testSimpleElementSelectorWithUtf8()
  {
    $html = '<div id="東とう京" class="ɹǝddɐɹʍ" style="color: white;"></div>';
    $css = '
    .ɹǝddɐɹʍ { display: none;}
    #東とう京{ color: black !important}
    ';
    $expected = '<div id="東とう京" class="ɹǝddɐɹʍ" style="display: none; color: black !important;"></div>';

    for ($i = 1; $i <= 2; $i++) { // keep this loop for quick&dirty performance tests
      $this->runHTMLToCSS($html, $css, $expected);
    }

  }

  /**
   * run html-to-css (and test it, if we set the "expected"-variable)
   *
   * @param string $html
   * @param string $css
   * @param string $expected
   * @param bool   $asXHTML
   *
   * @return string
   * @throws \voku\CssToInlineStyles\Exception
   */
  private function runHTMLToCSS($html, $css, $expected, $asXHTML = false)
  {
    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);

    $output = $cssToInlineStyles->convert($asXHTML);

    if ($expected) {
      self::assertSame($this->normalizeString($expected), $output);
    }

    return $output;
  }

  public function testSimpleCssSelector()
  {
    $html = '<a class="test-class">nodeContent</a>';
    $css = '.test-class { background-color: #aaa; text-decoration: none; }';
    $expected = '<a class="test-class" style="background-color: #aaa; text-decoration: none;">nodeContent</a>';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  /**
   * Parsing html with different media queries
   *
   * @param $html
   * @param $expected
   *
   * @dataProvider getMediaQueries
   */
  public function testMediaQueries($html, $expected)
  {
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setHTML($html);
    $this->runHTMLToCSS($html, '', $expected);
  }

  /**
   * @return array
   */
  public function getMediaQueries()
  {
    return array(
        array(
            '<html><head><style>@media (max-width: 600px) {.foo {margin: 0;}}</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media (max-width: 600px) {.foo {margin: 0;}}</style></head><body><div class="foo"></div></body></html>',
        ),
        array(
            '<html><head><style>@media tv and (min-width: 700px) and (orientation: landscape) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media tv and (min-width: 700px) and (orientation: landscape) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
        ),
        array(
            '<html><head><style>@media (min-width: 700px), handheld and (orientation: landscape) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media (min-width: 700px), handheld and (orientation: landscape) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
        ),
        array(
            '<html><head><style>@media not screen and (color), print and (color)</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media not screen and (color), print and (color)</style></head><body><div class="foo"></div></body></html>',
        ),
        array(
            '<html><head><style>@media screen and (min-aspect-ratio: 1/1) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media screen and (min-aspect-ratio: 1/1) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
        ),
        array(
            '<html><head><style>@media screen and (device-aspect-ratio: 16/9), screen and (device-aspect-ratio: 16/10) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
            '<html><head><style>@media screen and (device-aspect-ratio: 16/9), screen and (device-aspect-ratio: 16/10) {.foo {display: none;}}</style></head><body><div class="foo"></div></body></html>',
        ),
    );
  }

  public function testCssWithComments()
  {
    $css = <<<CSS
a {
    padding: 5px;
    display: block;
}
/* style the titles */
h1 {
    color: rebeccapurple;
}
/* end of title styles */
CSS;
    $result = $this->cssToInlineStyles->setHTML('<h1><a>foo</a></h1>')->setCSS($css)->convert();
    self::assertSame('<h1 style="color: rebeccapurple;"><a style="display: block; padding: 5px;">foo</a></h1>', $result);
  }

  public function testCssWithMediaQueries()
  {
    $css = <<<EOF
@media (max-width: 600px) {
    a {
        color: green;
    }
}
a {
  color: red;
}
EOF;
    $result = $this->cssToInlineStyles->setHTML('<a>foo</a>')->setCSS($css)->convert();
    self::assertSame('<a style="color: red;">foo</a>', $result);
  }

  public function testMediaQueryDisabledByDefault()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1 style="color: \'red\';">foo</h1><h1 style="color: \'red\';">foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testMediaQueryDisabled()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1 style="color: \'red\';">foo</h1><h1 style="color: \'red\';">foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(false);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testCssShorthandProperties()
  {
    $html = $this->file_get_contents(__DIR__ . '/fixtures/test6Html.html');
    $css = $this->file_get_contents(__DIR__ . '/fixtures/test6Style.css');
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test6Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles
        ->setUseInlineStylesBlock(true)
        ->setHTML($html)
        ->setCSS($css);
    $actual = $cssToInlineStyles->convert();

    self::assertSame($expected, $actual);
  }

  public function testDuplicateCssWithFoundation()
  {
    // we need some duplicate css for e.g. Outlook.com (https://www.emailonacid.com/blog/article/email-development/outlook.com-does-support-margins)

    $html = $this->file_get_contents(__DIR__ . '/fixtures/test7Html.html');
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test7Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles
        ->setUseInlineStylesBlock(true)
        ->setHTML($html);
    $actual = $cssToInlineStyles->convert();

    self::assertSame($expected, $actual);
  }

  public function testCssBigFile()
  {
    $html = $this->file_get_contents(__DIR__ . '/fixtures/test_big.html');
    $css = $this->file_get_contents(__DIR__ . '/fixtures/style_big.css');
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test_big_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles
        ->setUseInlineStylesBlock(true)
        ->setHTML($html)
        ->setCSS($css);
    $actual = $cssToInlineStyles->convert();

    self::assertSame($expected, $actual);
  }

  public function testKeepMediaQuery()
  {
    $html = $this->file_get_contents(__DIR__ . '/fixtures/test2Html.html');
    $css = $this->file_get_contents(__DIR__ . '/fixtures/test2Css.css');
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test2Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles
        ->setExcludeConditionalInlineStylesBlock(false)
        ->setUseInlineStylesBlock(true)
        ->setStripOriginalStyleTags(true)
        ->setExcludeMediaQueries(true)
        ->setExcludeCssCharset(true)
        ->setHTML($html)
        ->setCSS($css);
    $actual = $cssToInlineStyles->convert();

    self::assertSame($expected, $actual);
  }

  public function testKeepMediaQueryV2()
  {
    $html = $this->file_get_contents(__DIR__ . '/fixtures/test3Html.html');
    $css = '';
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test3Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles->setExcludeConditionalInlineStylesBlock(false);
    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $cssToInlineStyles->setStripOriginalStyleTags(true);
    $cssToInlineStyles->setExcludeMediaQueries(true);
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);
    $actual = $cssToInlineStyles->convert();
    self::assertSame($expected, $actual);
  }

  public function testLoadCssFile()
  {
    $html = file_get_contents(__DIR__ . '/fixtures/test4Html.html');
    $css = '';
    $expected = file_get_contents(__DIR__ . '/fixtures/test4Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles->setExcludeConditionalInlineStylesBlock(false);
    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $cssToInlineStyles->setStripOriginalStyleTags(true);
    $cssToInlineStyles->setExcludeMediaQueries(true);
    $cssToInlineStyles->setLoadCSSFromHTML(true);
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);
    $actual = $cssToInlineStyles->convert(false, 0, __DIR__ . '/fixtures/');
    self::assertSame($this->normalizeString($expected), $this->normalizeString($actual));
  }

  public function testLoadCssFileV5()
  {
    $html = $this->file_get_contents(__DIR__ . '/fixtures/test5Html.html');
    $css = '';
    $expected = $this->file_get_contents(__DIR__ . '/fixtures/test5Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles->setExcludeConditionalInlineStylesBlock(true);
    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $cssToInlineStyles->setStripOriginalStyleTags(false);
    $cssToInlineStyles->setExcludeMediaQueries(true);
    $cssToInlineStyles->setLoadCSSFromHTML(false);
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);
    $actual = $cssToInlineStyles->convert(false);
    self::assertSame($expected, $actual);
  }

  public function testMediaQuery()
  {
    $html = '<html><body><style>#outlook a{padding:0}body{width:100%!important;min-width:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}.ExternalClass{width:100%}.ExternalClass,.ExternalClass div,.ExternalClass font,.ExternalClass p,.ExternalClass span,.ExternalClass td{line-height:100%}#backgroundTable{margin:0;padding:0;width:100%!important;line-height:100%!important}img{outline:0;text-decoration:none;-ms-interpolation-mode:bicubic;width:auto;max-width:100%;float:left;clear:both;display:block}center{width:100%;min-width:580px}a img{border:none}table{border-spacing:0;border-collapse:collapse}td{word-break:break-word;-webkit-hyphens:auto;-moz-hyphens:auto;hyphens:auto;border-collapse:collapse!important}table,td,tr{padding:0;vertical-align:top;text-align:left}hr{color:#d9d9d9;background-color:#d9d9d9;height:1px;border:none}table.body{height:100%;width:100%}table.container{width:580px;margin:0 auto;text-align:inherit}table.row{padding:0;width:100%;position:relative}table.container table.row{display:block}td.wrapper{padding:10px 20px 0 0;position:relative}table.column,table.columns{margin:0 auto}table.column td,table.columns td{padding:0 0 10px}table.column td.sub-column,table.column td.sub-columns,table.columns td.sub-column,table.columns td.sub-columns{padding-right:10px}td.sub-column,td.sub-columns{min-width:0}table.container td.last,table.row td.last{padding-right:0}table.one{width:30px}table.two{width:80px}table.three{width:130px}table.four{width:180px}table.five{width:230px}table.six{width:280px}table.seven{width:330px}table.eight{width:380px}table.nine{width:430px}table.ten{width:480px}table.eleven{width:530px}table.twelve{width:580px}table.one center{min-width:30px}table.two center{min-width:80px}table.three center{min-width:130px}table.four center{min-width:180px}table.five center{min-width:230px}table.six center{min-width:280px}table.seven center{min-width:330px}table.eight center{min-width:380px}table.nine center{min-width:430px}table.ten center{min-width:480px}table.eleven center{min-width:530px}table.twelve center{min-width:580px}table.one .panel center{min-width:10px}table.two .panel center{min-width:60px}table.three .panel center{min-width:110px}table.four .panel center{min-width:160px}table.five .panel center{min-width:210px}table.six .panel center{min-width:260px}table.seven .panel center{min-width:310px}table.eight .panel center{min-width:360px}table.nine .panel center{min-width:410px}table.ten .panel center{min-width:460px}table.eleven .panel center{min-width:510px}table.twelve .panel center{min-width:560px}.body .column td.one,.body .columns td.one{width:8.333333%}.body .column td.two,.body .columns td.two{width:16.666666%}.body .column td.three,.body .columns td.three{width:25%}.body .column td.four,.body .columns td.four{width:33.333333%}.body .column td.five,.body .columns td.five{width:41.666666%}.body .column td.six,.body .columns td.six{width:50%}.body .column td.seven,.body .columns td.seven{width:58.333333%}.body .column td.eight,.body .columns td.eight{width:66.666666%}.body .column td.nine,.body .columns td.nine{width:75%}.body .column td.ten,.body .columns td.ten{width:83.333333%}.body .column td.eleven,.body .columns td.eleven{width:91.666666%}.body .column td.twelve,.body .columns td.twelve{width:100%}td.offset-by-one{padding-left:50px}td.offset-by-two{padding-left:100px}td.offset-by-three{padding-left:150px}td.offset-by-four{padding-left:200px}td.offset-by-five{padding-left:250px}td.offset-by-six{padding-left:300px}td.offset-by-seven{padding-left:350px}td.offset-by-eight{padding-left:400px}td.offset-by-nine{padding-left:450px}td.offset-by-ten{padding-left:500px}td.offset-by-eleven{padding-left:550px}td.expander{visibility:hidden;width:0;padding:0!important}table.column .text-pad,table.columns .text-pad{padding-left:10px;padding-right:10px}table.column .left-text-pad,table.column .text-pad-left,table.columns .left-text-pad,table.columns .text-pad-left{padding-left:10px}table.column .right-text-pad,table.column .text-pad-right,table.columns .right-text-pad,table.columns .text-pad-right{padding-right:10px}.block-grid{width:100%;max-width:580px}.block-grid td{display:inline-block;padding:10px}.two-up td{width:270px}.three-up td{width:173px}.four-up td{width:125px}.five-up td{width:96px}.six-up td{width:76px}.seven-up td{width:62px}.eight-up td{width:52px}h1.center,h2.center,h3.center,h4.center,h5.center,h6.center,table.center,td.center{text-align:center}span.center{display:block;width:100%;text-align:center}img.center{margin:0 auto;float:none}.hide-for-desktop,.show-for-small{display:none}body,h1,h2,h3,h4,h5,h6,p,table.body,td{color:#222;font-family:Helvetica,Arial,sans-serif;font-weight:400;padding:0;margin:0;text-align:left}h1,h2,h3,h4,h5,h6{word-break:normal;line-height:1.7}h1{font-size:40px}h2{font-size:36px}h3{font-size:32px}h4{font-size:28px}h5{font-size:24px}h6{font-size:20px}body,p,table.body,td{font-size:14px;line-height:19px}p.lead,p.lede,p.leed{font-size:18px;line-height:21px}p{margin-bottom:10px}small{font-size:10px}a{color:#2ba6cb;text-decoration:none}a:active,a:hover{color:#2795b6!important}a:visited{color:#2ba6cb!important}h1 a,h2 a,h3 a,h4 a,h5 a,h6 a{color:#2ba6cb}h1 a:active,h1 a:visited,h2 a:active,h2 a:visited,h3 a:active,h3 a:visited,h4 a:active,h4 a:visited,h5 a:active,h5 a:visited,h6 a:active,h6 a:visited{color:#2ba6cb!important}.panel{background:#f2f2f2;border:1px solid #d9d9d9;padding:10px!important}.sub-grid table{width:100%}.sub-grid td.sub-columns{padding-bottom:0}table.button,table.large-button,table.medium-button,table.small-button,table.tiny-button{width:100%;overflow:hidden}table.button td,table.large-button td,table.medium-button td,table.small-button td,table.tiny-button td{display:block;width:auto!important;text-align:center;background:#2ba6cb;border:1px solid #2284a1;color:#fff;padding:8px 0}table.tiny-button td{padding:5px 0 4px}table.small-button td{padding:8px 0 7px}table.medium-button td{padding:12px 0 10px}table.large-button td{padding:21px 0 18px}table.button td a,table.large-button td a,table.medium-button td a,table.small-button td a,table.tiny-button td a{font-weight:700;text-decoration:none;font-family:Helvetica,Arial,sans-serif;color:#fff;font-size:16px}table.tiny-button td a{font-size:12px;font-weight:400}table.small-button td a{font-size:16px}table.medium-button td a{font-size:20px}table.large-button td a{font-size:24px}table.button:active td,table.button:hover td,table.button:visited td{background:#2795b6!important}table.button:active td a,table.button:hover td a,table.button:visited td a{color:#fff!important}table.button:hover td,table.large-button:hover td,table.medium-button:hover td,table.small-button:hover td,table.tiny-button:hover td{background:#2795b6!important}table.button td a:visited,table.button:active td a,table.button:hover td a,table.large-button td a:visited,table.large-button:active td a,table.large-button:hover td a,table.medium-button td a:visited,table.medium-button:active td a,table.medium-button:hover td a,table.small-button td a:visited,table.small-button:active td a,table.small-button:hover td a,table.tiny-button td a:visited,table.tiny-button:active td a,table.tiny-button:hover td a{color:#fff!important}table.secondary td{background:#e9e9e9;border-color:#d0d0d0;color:#555}table.secondary td a{color:#555}table.secondary:hover td{background:#d0d0d0!important;color:#555}table.secondary td a:visited,table.secondary:active td a,table.secondary:hover td a{color:#555!important}table.success td{background:#5da423;border-color:#457a1a}table.success:hover td{background:#457a1a!important}table.alert td{background:#c60f13;border-color:#970b0e}table.alert:hover td{background:#970b0e!important}table.radius td{-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}table.round td{-webkit-border-radius:500px;-moz-border-radius:500px;border-radius:500px}body.outlook p{display:inline!important}@media only screen and (max-width:600px){table[class=body] img{width:auto!important;height:auto!important}table[class=body] center{min-width:0!important}table[class=body] .container{width:95%!important}table[class=body] .row{width:100%!important;display:block!important}table[class=body] .wrapper{display:block!important;padding-right:0!important}table[class=body] .column,table[class=body] .columns{table-layout:fixed!important;float:none!important;width:100%!important;padding-right:0!important;padding-left:0!important;display:block!important}table[class=body] .wrapper.first .column,table[class=body] .wrapper.first .columns{display:table!important}table[class=body] table.column td,table[class=body] table.columns td{width:100%!important}table[class=body] .column td.one,table[class=body] .columns td.one{width:8.333333%!important}table[class=body] .column td.two,table[class=body] .columns td.two{width:16.666666%!important}table[class=body] .column td.three,table[class=body] .columns td.three{width:25%!important}table[class=body] .column td.four,table[class=body] .columns td.four{width:33.333333%!important}table[class=body] .column td.five,table[class=body] .columns td.five{width:41.666666%!important}table[class=body] .column td.six,table[class=body] .columns td.six{width:50%!important}table[class=body] .column td.seven,table[class=body] .columns td.seven{width:58.333333%!important}table[class=body] .column td.eight,table[class=body] .columns td.eight{width:66.666666%!important}table[class=body] .column td.nine,table[class=body] .columns td.nine{width:75%!important}table[class=body] .column td.ten,table[class=body] .columns td.ten{width:83.333333%!important}table[class=body] .column td.eleven,table[class=body] .columns td.eleven{width:91.666666%!important}table[class=body] .column td.twelve,table[class=body] .columns td.twelve{width:100%!important}table[class=body] td.offset-by-eight,table[class=body] td.offset-by-eleven,table[class=body] td.offset-by-five,table[class=body] td.offset-by-four,table[class=body] td.offset-by-nine,table[class=body] td.offset-by-one,table[class=body] td.offset-by-seven,table[class=body] td.offset-by-six,table[class=body] td.offset-by-ten,table[class=body] td.offset-by-three,table[class=body] td.offset-by-two{padding-left:0!important}table[class=body] table.columns td.expander{width:1px!important}table[class=body] .right-text-pad,table[class=body] .text-pad-right{padding-left:10px!important}table[class=body] .left-text-pad,table[class=body] .text-pad-left{padding-right:10px!important}table[class=body] .hide-for-small,table[class=body] .show-for-desktop{display:none!important}table[class=body] .hide-for-desktop,table[class=body] .show-for-small{display:inherit!important}}@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; min-width: 100%; width: 100%!important; color: #222; font-family: Helvetica,Arial,sans-serif; font-weight: 400; margin: 0; padding: 0; text-align: left; font-size: 14px; line-height: 19px;"><div class="test"><h1 style="font-family: Helvetica,Arial,sans-serif; font-weight: 400; margin: 0; padding: 0; text-align: left; line-height: 1.7; word-break: normal; font-size: 40px; color: \'red\';">foo</h1><h1 style="font-family: Helvetica,Arial,sans-serif; font-weight: 400; margin: 0; padding: 0; text-align: left; line-height: 1.7; word-break: normal; font-size: 40px; color: \'red\';">foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(false);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testMediaQueryV2()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } }</style><div class="test"><h1 style="color: \'red\';">foo</h1><h1 style="color: \'red\';">foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testMediaQueryV3()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } @media (max-width: 500px) { .test { top: 1rem; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } }
@media (max-width: 500px) { .test { top: 1rem; } }</style><div class="test"><h1 style="color: \'red\';">foo</h1><h1 style="color: \'red\';">foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testSimpleIdSelector()
  {
    $html = '<img id="IMG1">';
    $css = '#IMG1 { border: 1px solid red; }';
    $expected = '<img id="IMG1" style="border: 1px solid red;">';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testInlineStylesBlock()
  {
    $html = <<<EOF
<style type="text/css">
  a {
    padding: 10px;
    margin: 0;
  }
</style>
<a></a>
EOF;
    $expected = '<a style="margin: 0; padding: 10px;"></a>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setHTML($html);
    $actual = $this->findAndSaveNode($this->cssToInlineStyles->convert(), '//a');
    self::assertSame($expected, $actual);
  }

  /**
   * find and save node
   *
   * @param string $html
   * @param string $query
   *
   * @return null|string
   */
  private function findAndSaveNode($html, $query)
  {
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    $nodelist = $xpath->query($query);
    if ($nodelist->length > 0) {
      $node = $nodelist->item(0);

      /** @noinspection PhpMethodParametersCountMismatchInspection */
      return $dom->saveHTML($node);
    } else {
      return null;
    }
  }

  public function testStripOriginalStyleTags()
  {
    $html = <<<EOF
<style type="text/css">
  a {
    padding: 10px;
    margin: 0;
  }
</style>
<a></a>
EOF;
    $expected = '<a></a>';
    $this->cssToInlineStyles->setStripOriginalStyleTags();
    $this->cssToInlineStyles->setHTML($html);
    $actual = $this->findAndSaveNode($this->cssToInlineStyles->convert(), '//a');
    self::assertSame($expected, $actual);

    self::assertNull($this->findAndSaveNode($actual, '//style'));
  }

  public function testSpecificity()
  {
    $html = <<<EOF
<a class="one" id="ONE" style="padding: 100px;">
  <img class="two" id="TWO" style="padding-top: 30px;">
  <img class="three" id="THREE">
  <img class="four" id="FOUR" style="margin-left: 100px;">
  <img class="five" id="FIVE" style="padding-left: 10px; padding: 100px;">
</a>
EOF;
    $css = <<<EOF
a.one  {
  border-bottom: 2px;
  height: 20px;
}
a {
  border: 1px solid red;
  padding: 10px;
  margin: 20px;
  width: 10px !important;
  height: 10px;
}
.one {
  padding: 15px;
  width: 20px !important;
  height: 5px;
}
#ONE {
  margin: 10px;
  width: 30px;
}
.two {
  padding-top: 20px;
}
img {
  padding: 0;
}
a img {
  border: none;
}
img {
  border: 2px solid green;
  padding-bottom: 20px;
}
img {
  padding: 0;
}
#THREE {
  padding-left: 10px;
}
.three {
  padding: 100px;
}
.four {
  margin: 10px;
}
.five {
  padding: 15px;
}
#FIVE {
  padding-left: 20px;
}
EOF;
    $expected = <<<EOF
<a class="one" id="ONE" style="border: 1px solid red; width: 20px !important; border-bottom: 2px; height: 20px; margin: 10px; padding: 100px;">  <img class="two" id="TWO" style="padding-bottom: 20px; padding: 0; border: none; padding-top: 30px;"><img class="three" id="THREE" style="padding-bottom: 20px; border: none; padding: 100px; padding-left: 10px;"><img class="four" id="FOUR" style="padding-bottom: 20px; padding: 0; border: none; margin: 10px; margin-left: 100px;"><img class="five" id="FIVE" style="padding-bottom: 20px; border: none; padding-left: 10px; padding: 100px;"></a>
EOF;
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testMergeOriginalStyles()
  {
    $html = '<p style="padding: 20px; margin-top: 10px;">text</p>';
    $css = <<<EOF
p {
  margin-top: 20px;
  text-indent: 1em;
}
EOF;
    $expected = '<p style="text-indent: 1em; padding: 20px; margin-top: 10px;">text</p>';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testXHTMLOutput()
  {
    $html = '<a><img></a>';
    $css = 'a { display: block; }';

    $this->cssToInlineStyles->setHTML($html);
    $this->cssToInlineStyles->setCSS($css);
    $actual = $this->cssToInlineStyles->convert(true);

    self::assertContains('<img></img>', $actual);
  }

  public function testCleanup()
  {
    $html = '<div id="id" class="className"> id="foo" class="bar" </div>';
    $css = ' #id { display: inline; } .className { margin-right: 10px; }';
    $expected = '<div style="margin-right: 10px; display: inline;"> id="foo" class="bar" </div>';
    $this->cssToInlineStyles->setCleanup();
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testCleanupWithoutStyleTagCleanup()
  {
    $html = '<style>div { top: 1em; }</style><style>div { left: 1em; }</style><div id="id" class="className"> id="foo" class="bar" </div>';
    $css = ' #id { display: inline; } .className { margin-right: 10px; }';
    $expected = '<head><style>div { top: 1em; }</style><style>div { left: 1em; }</style></head><div style="top: 1em; left: 1em; margin-right: 10px; display: inline;"> id="foo" class="bar" </div>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(false);
    $this->cssToInlineStyles->setCleanup(true);
    $this->cssToInlineStyles->setHTML($html);
    $this->cssToInlineStyles->setCSS($css);
    $actual = $this->cssToInlineStyles->convert();
    self::assertSame($this->normalizeString($expected), $actual);
  }

  public function testCleanupWithStyleTagCleanup()
  {
    $html = '<style class="cleanup">div { top: 1em; }</style><style>.cleanup { top: 1em; } div { left: 1em; }</style><div id="id" class="className"> id="foo" class="bar" </div>';
    $css = ' #id { display: inline; } .className { margin-right: 10px; }';
    $expected = '<head><style>.cleanup { top: 1em; } div { left: 1em; }</style></head><div style="left: 1em; margin-right: 10px; display: inline;"> id="foo" class="bar" </div>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(false);
    $this->cssToInlineStyles->setCleanup(true);
    $this->cssToInlineStyles->setHTML($html);
    $this->cssToInlineStyles->setCSS($css);
    $actual = $this->cssToInlineStyles->convert();
    self::assertSame($this->normalizeString($expected), $actual);
  }

  public function testEqualSpecificity()
  {
    $html = '<img class="one">';
    $css = ' .one { display: inline; } a > strong {} a {} a {} a {} a {} a {} a {}a {} img { display: block; }';
    $expected = '<img class="one" style="display: inline;">';
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testInvalidSelector()
  {
    $html = '<p></p>';
    $css = ' p&@*$%& { display: inline; }';
    $expected = $html;
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testBoilerplateEmail()
  {
    $html = file_get_contents(__DIR__ . '/fixtures/test1Html.html');
    $css = '';
    $expected = file_get_contents(__DIR__ . '/fixtures/test1Html_result.html');

    $cssToInlineStyles = $this->cssToInlineStyles;
    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);
    $actual = $cssToInlineStyles->convert();
    self::assertSame($this->normalizeString($expected), $this->normalizeString($actual));
  }

  public function testEncodingIso()
  {
    $testString = file_get_contents(__DIR__ . '/fixtures/test1Latin.txt');

    $html = '<p>' . $testString . '</p>';
    $css = '';
    $expected = '';

    $this->cssToInlineStyles->setEncoding('ISO-8859-1');
    $result = $this->runHTMLToCSS($html, $css, $expected);

    self::assertContains('<p>H', $result);
    self::assertContains('Iñtërnâtiônàlizætiøn', $result);
  }

  public function testEncodingUtf8()
  {
    $testString = file_get_contents(__DIR__ . '/fixtures/test1Utf8.txt');
    self::assertContains('Iñtërnâtiônàlizætiøn', $testString);

    $html = '<p>' . $testString . '</p>';
    $css = '';
    $expected = '';

    $this->cssToInlineStyles->setEncoding('UTF-8');
    $result = $this->runHTMLToCSS($html, $css, $expected);

    self::assertContains('<p>Hírek', $result);
    self::assertContains('Iñtërnâtiônàlizætiøn', $result);
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessage No HTML provided.
   */
  public function testNoHtml()
  {
    $this->cssToInlineStyles->setHTML('');
    $this->cssToInlineStyles->setCSS('');

    $this->cssToInlineStyles->convert(true);
  }

  public function testXMLHeaderIsRemoved()
  {
    $html = '<html><body><p>Foo</p></body>';

    $this->cssToInlineStyles->setHTML($html);
    $this->cssToInlineStyles->setCSS('');

    self::assertNotContains('<?xml', $this->cssToInlineStyles->convert(true));
  }

  public function testXMLHeaderIsRemovedv2()
  {
    $html = '<html><body><p>Foo</p></body>';
    $expected = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html>
  <body>
    <p>Foo</p>
  </body>
</html>';
    $this->cssToInlineStyles->setHTML($html);
    $this->cssToInlineStyles->setCSS('');
    $actual = $this->cssToInlineStyles->convert(true);
    self::assertSame($this->normalizeString($expected), $actual);
  }

  public function testExcludeConditionalInlineStylesBlock()
  {
    $html = '<html><body><style>div { width: 200px; width: 222px; }</style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style></style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><div class="test" style="width: 200px; width: 222px;"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setExcludeConditionalInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);

    // ---

    $html = '<html><body><style>div { width: 200px; width: 222px; }</style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><!-- <style> .test { width: 0 !important; } </style> --><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style></style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><!-- <style> .test { width: 0 !important; } </style> --><div class="test" style="width: 200px; width: 222px;"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setExcludeConditionalInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testExcludeConditionalInlineStylesBlockFalse()
  {
    $html = '<html><body><style>div { width: 200px; width: 222px; }</style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style></style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><div class="test" style="width: 222px; top: 1em;"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setExcludeConditionalInlineStylesBlock(false);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);

    // ---

    $html = '<html><body><style>div { width: 200px; width: 222px; }</style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><!-- <style> .test { width: 0 !important; } </style> --><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style></style><!--[if gte mso 9]><STyle>.test { top: 1em; } </STyle><![endif]--><!-- <style> .test { width: 0 !important; } </style> --><div class="test" style="width: 222px; top: 1em;"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setExcludeConditionalInlineStylesBlock(false);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testBug83()
  {
    $html = '<html><body><style>div { width: 200px; _width: 222px; width: 222px; }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '';
    $expected = '<html><body><style></style><div class="test" style="_width: 222px; width: 200px; width: 222px;"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testBug92()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" } .bg {
background-image: url(\'data:image/jpg;base64,/9j/4QAYRXhpZgAASUkqAAgAA//Z\'); } </style><div class="test"><h1>foo</h1><h1>foo2</h1><table class="bg"></table></div></body></html>';
    $css = '';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } }</style><div class="test"><h1 style="color: \'red\';">foo</h1><h1 style="color: \'red\';">foo2</h1><table class="bg" style="background-image: url(\'data:image/jpg;base64,/9j/4QAYRXhpZgAASUkqAAgAA//Z\');"></table></div></body></html>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testBug99()
  {
    $html = '<p>This is a test heading with various things such as <strong>bold</strong>, <span style="text-decoration: underline;"><strong>bold-underline</strong></span><strong></strong>, <em>italics</em>, and various other cool things.</p><p>Штампы гіст Эйн тэст!</p>';
    $css = 'p { color: "red" }';
    $expected = '<p style="color: \'red\';">This is a test heading with various things such as <strong>bold</strong>, <span style="text-decoration: underline;"><strong>bold-underline</strong></span><strong></strong>, <em>italics</em>, and various other cool things.</p><p style="color: \'red\';">Штампы гіст Эйн тэст!</p>';
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->cssToInlineStyles->setStripOriginalStyleTags(true);
    $this->cssToInlineStyles->setExcludeMediaQueries(true);
    $this->runHTMLToCSS($html, $css, $expected);
  }

  public function testCssRulesResetDuringSecondLoad()
  {
    $html = '<p></p>';
    $css = 'p { margin: 10px; }';
    $expected = '<p style="margin: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);

    $html = '<p></p>';
    $css = 'p { padding: 10px; margin: 10px; }';
    $expected = '<p style="margin: 10px; padding: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);

    $html = '<p></p>';
    $css = 'p { padding: 10px; }';
    $expected = '<p style="padding: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);

  }

  public function testConstructor()
  {
    $html = '<html><body><style>@media (max-width: 600px) { .test { display: none; } } h1 { color : "red" }</style><div class="test"><h1>foo</h1><h1>foo2</h1></div></body></html>';
    $css = '.test { color: "red" };';
    $expected = '<html><body><style>@media (max-width: 600px) { .test { display: none; } }</style><div class="test" style="color: \'red\';"><h1>foo</h1><h1>foo2</h1></div></body></html>';

    $cssToInlineStyles = new CssToInlineStyles($html, $css);

    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $cssToInlineStyles->setStripOriginalStyleTags(true);
    $cssToInlineStyles->setExcludeMediaQueries(true);

    // first convert
    $actual = $cssToInlineStyles->convert(true);
    self::assertSame($this->normalizeString($expected), $actual);

    // second convert
    $actual = $cssToInlineStyles->convert(true);
    self::assertSame($this->normalizeString($expected), $actual);
  }

  public function testCssRulesInlineResetDuringSecondLoad()
  {
    $html = '<p></p>';
    $css = 'p { margin: 10px; }';
    $expected = '<p style="margin: 10px;"></p>';
    $this->runHTMLToCSS($html, $css, $expected);
    $html = <<<HTML
<style>
    p {
        padding: 10px;
    }
</style>
<p></p>
HTML;
    $css = 'p { margin: 10px; }';
    $this->runHTMLToCSS($html, $css, '<head><style>    p {        padding: 10px;    }</style></head><p style="margin: 10px;"></p>');
    $this->cssToInlineStyles->setUseInlineStylesBlock(true);
    $this->runHTMLToCSS($html, $css, '<head><style>    p {        padding: 10px;    }</style></head><p style="margin: 10px; padding: 10px;"></p>');
  }

  public function testSimpleStyleTagsInHtml()
  {
    $expected = 'p { color: #F00; }' . "\n";
    self::assertSame(
        $expected,
        $this->cssToInlineStyles->getCssFromInlineHtmlStyleBlock(
            <<<EOF
                    <html>
    <head>
        <style>
            p { color: #F00; }
        </style>
    </head>
    <body>
        <p>foo</p>
    </body>
    </html>
EOF
        )
    );
  }

  public function testMultipleStyleTagsInHtml()
  {
    $expected = 'p { color: #F00; }' . "\n" . 'p { color: #0F0; }' . "\n";
    self::assertSame(
        $expected,
        $this->cssToInlineStyles->getCssFromInlineHtmlStyleBlock(
            <<<EOF
                    <html>
    <head>
        <style>
            p { color: #F00; }
        </style>
    </head>
    <body>
        <style>
            p { color: #0F0; }
        </style>
        <p>foo</p>
    </body>
    </html>
EOF
        )
    );
  }

  public function testHtmlEncoding()
  {
    $text = 'Žluťoučký kůň pije pivo nebo jak to je dál @ €';
    $cssToInlineStyles = new CssToInlineStyles($text, '');
    $result = $cssToInlineStyles->convert(true);
    self::assertSame($text, $result);

    // ---

    $expectedText = 'Žluťoučký kůň pije pivo nebo jak to je dál @ €';
    $result = $cssToInlineStyles->convert(false);
    self::assertSame($expectedText, $result);
  }

  public function testSpecialCharacters()
  {
    $text = '1 &lt; 2';
    self::assertEquals($text, $this->cssToInlineStyles->setHTML($text)->convert());
  }

  public function testSpecialCharactersExplicit()
  {
    $text = '&amp;lt;script&amp;&gt;';
    self::assertEquals($text, $this->cssToInlineStyles->setHTML($text)->convert());
  }

  /**
   * @param $filename
   *
   * @return mixed
   */
  protected function file_get_contents($filename)
  {
    $string = UTF8::file_get_contents($filename);

    return $this->normalizeString($string);
  }

  /**
   * @param $string
   *
   * @return mixed
   */
  protected function normalizeString($string)
  {
    return str_replace(array("\r\n", "\r"), "\n", $string);
  }

  public function testStyleTagsWithAttributeInHtml()
  {
    $css = 'p { color: #F00; }' . "\n";

    $html = <<<HTML
                    <html>
    <head>
        <!-- weird tag name starting with style -->
        <stylesheet></stylesheet>
        <style type="text/css">
            body { color: #F00; }
        </style>
    </head>
    <body>
        <p>foo</p>
    </body>
    </html>
HTML;

    $expected = <<<EXPECTED
<html>
    <head>
        <!-- weird tag name starting with style -->
        <stylesheet></stylesheet>
        <style type="text/css">
            body { color: #F00; }
        </style>
    </head>
    <body style="color: #F00;">
        <p style="color: #F00;">foo</p>
    </body>
    </html>
EXPECTED;

    $result = $this->cssToInlineStyles
        ->setUseInlineStylesBlock(true)
        ->setHTML($html)
        ->setCSS($css)
        ->convert();

    self::assertEquals($this->normalizeString($expected), $result);
  }

  public function testIssue25()
  {
    $css = '
    *{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}

    body, html {margin: 0;padding: 0;width: 100%;}
    div.row {width: 100%;display: block;clear: both;}
    div.col-25 { width: 25%;float: left; display: block;padding: 10px;}
    div.content {width:100%; background: #eee;padding: 10px;}
    
    @media only screen and (min-width: 360px) {
      div.col-25 { width: 100%; float: left; display: block;padding: 10px;}
    }
    
    @media only screen and (min-width: 120px) {
      div.col-25 { width: 100%; display: block; padding: 15px;}
    }
    ';

    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
      <title>Testing</title>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    </head>
    <body>
    
    <div class="row">
      <div class="col-25">
        <div class="content">Hello World!</div>
      </div>
      <div class="col-25">
        <div class="content">Hello World!</div>
      </div>
      <div class="col-25">
        <div class="content">Hello World!</div>
      </div>
      <div class="col-25">
        <div class="content">Hello World!</div>
      </div>
    </div>
    
    </body>
    </html>
HTML;

    $expected = <<<EXPECTED
<!DOCTYPE html>
<html style="margin: 0; padding: 0; width: 100%;">
    <head>
      <title>Testing</title>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    
<style type="text/css">
@media only screen and (min-width: 360px) {
      div.col-25 { width: 100%; float: left; display: block;padding: 10px;}
    }
@media only screen and (min-width: 120px) {
      div.col-25 { width: 100%; display: block; padding: 15px;}
    }
</style>
</head>
    <body style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; margin: 0; padding: 0; width: 100%;">
    
    <div class="row" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; clear: both; display: block; width: 100%;">
      <div class="col-25" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; display: block; float: left; padding: 10px; width: 25%;">
        <div class="content" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; background: #eee; padding: 10px; width: 100%;">Hello World!</div>
      </div>
      <div class="col-25" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; display: block; float: left; padding: 10px; width: 25%;">
        <div class="content" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; background: #eee; padding: 10px; width: 100%;">Hello World!</div>
      </div>
      <div class="col-25" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; display: block; float: left; padding: 10px; width: 25%;">
        <div class="content" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; background: #eee; padding: 10px; width: 100%;">Hello World!</div>
      </div>
      <div class="col-25" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; display: block; float: left; padding: 10px; width: 25%;">
        <div class="content" style="-moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; background: #eee; padding: 10px; width: 100%;">Hello World!</div>
      </div>
    </div>
    
    </body>
    </html>
EXPECTED;

    $cssToInlineStyles = new CssToInlineStyles();

    $result = $cssToInlineStyles
        ->setExcludeMediaQueries(false)
        ->setHTML($html)
        ->setCSS($css)
        ->convert();

    self::assertEquals($this->normalizeString($expected), $this->normalizeString($result));
  }
}
