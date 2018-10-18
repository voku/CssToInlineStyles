[![Build Status](https://travis-ci.org/voku/CssToInlineStyles.svg?branch=master)](https://travis-ci.org/voku/CssToInlineStyles)
[![codecov.io](http://codecov.io/github/voku/CssToInlineStyles/coverage.svg?branch=master)](http://codecov.io/github/voku/CssToInlineStyles?branch=master)
[![Coverage Status](https://coveralls.io/repos/voku/CssToInlineStyles/badge.svg)](https://coveralls.io/r/voku/CssToInlineStyles)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/voku/CssToInlineStyles/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/voku/CssToInlineStyles/?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/47caa384f390472cbff1f1d46c86fd8e)](https://www.codacy.com/app/voku/CssToInlineStyles)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/2468e72e-dc91-4c16-9259-20c5e2c0d2a9/mini.png)](https://insight.sensiolabs.com/projects/2468e72e-dc91-4c16-9259-20c5e2c0d2a9)
[![Latest Stable Version](https://poser.pugx.org/voku/css-to-inline-styles/v/stable)](https://packagist.org/packages/voku/css-to-inline-styles) 
[![Total Downloads](https://poser.pugx.org/voku/css-to-inline-styles/downloads)](https://packagist.org/packages/voku/css-to-inline-styles) 
[![Latest Unstable Version](https://poser.pugx.org/voku/css-to-inline-styles/v/unstable)](https://packagist.org/packages/voku/css-to-inline-styles) 
[![PHP 7 ready](http://php7ready.timesplinter.ch/voku/CssToInlineStyles/badge.svg)](https://travis-ci.org/voku/CssToInlineStyles)
[![License](https://poser.pugx.org/voku/css-to-inline-styles/license)](https://packagist.org/packages/voku/css-to-inline-styles)

# CssToInlineStyles class

WARNING: this is only a Extended-Fork of "https://github.com/tijsverkoyen/CssToInlineStyles"

> CssToInlineStyles is a class that enables you to convert HTML-pages/files into
> HTML-pages/files with inline styles. This is very usefull when you're sending
> emails.

## About

PHP CssToInlineStyles is a class to convert HTML into HTML with inline styles.

## Installation

The recommended installation way is through [Composer](https://getcomposer.org).

```bash
$ composer require voku/css-to-inline-styles
```

## Example

    use voku\CssToInlineStyles\CssToInlineStyles;

    // Convert HTML + CSS to HTML with inlined CSS
    $cssToInlineStyles= new CssToInlineStyles();
    $cssToInlineStyles->setHTML($html);
    $cssToInlineStyles->setCSS($css);
    $html = $cssToInlineStyles->convert();

    // Or use inline-styles blocks from the HTML as CSS
    $cssToInlineStyles = new CssToInlineStyles($html);
    $cssToInlineStyles->setUseInlineStylesBlock(true);
    $html = $cssToInlineStyles->convert();
    
    // Or use linked files from the HTML as CSS
    $cssToInlineStyles = new CssToInlineStyles($html);
    $cssToInlineStyles->setLoadCSSFromHTML(true);
    $html = $cssToInlineStyles->convert(false, 0, __DIR__ . '/../tests/');

    
## Documentation

The following properties exists and have set methods available:

Property | Default | Description
-------|---------|------------
cleanup|false|Should the generated HTML be cleaned?
useInlineStylesBlock |false|Use inline-styles block as CSS.
stripOriginalStyleTags |false|Strip original style tags.
excludeMediaQueries |true|Exclude media queries from extra "css" and keep media queries for inline-styles blocks.
excludeConditionalInlineStylesBlock |true|Exclude conditional inline-style blocks.

## Warning

Also if the default is ```cleanup === false```, you maybe need to use this feature, because Outlook has some special features where the inline-CSS will be ignored and it will fallback to use only the CSS-Class- or ID-properties.

```php
$cssToInlineStyles->setCleanup(true);
```

## Known issues

* no support for pseudo selectors

