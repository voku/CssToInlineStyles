# CssToInlineStyles class

[![Build Status](https://travis-ci.org/voku/CssToInlineStyles.svg?branch=master)](https://travis-ci.org/voku/CssToInlineStyles)
[![Coverage Status](https://coveralls.io/repos/voku/CssToInlineStyles/badge.svg)](https://coveralls.io/r/voku/CssToInlineStyles)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/voku/CssToInlineStyles/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/voku/CssToInlineStyles/?branch=master)
[![Codacy Badge](https://www.codacy.com/project/badge/997c9bb10d1c4791967bdf2e42013e8e)](https://www.codacy.com/app/voku/CssToInlineStyles)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/be5bf087-366c-463e-ac9f-c184db6347ba/mini.png)](https://insight.sensiolabs.com/projects/be5bf087-366c-463e-ac9f-c184db6347ba)
[![Dependency Status](https://www.versioneye.com/php/voku:CssToInlineStyles/dev-master/badge.svg)](https://www.versioneye.com/php/voku:CssToInlineStyles/dev-master)
[![Reference Status](https://www.versioneye.com/php/voku:CssToInlineStyles/reference_badge.svg?style=flat)](https://www.versioneye.com/php/voku:CssToInlineStyles/references)
[![Total Downloads](https://poser.pugx.org/voku/CssToInlineStyles/downloads)](https://packagist.org/packages/voku/CssToInlineStyles)
[![License](https://poser.pugx.org/voku/CssToInlineStyles/license.svg)](https://packagist.org/packages/voku/CssToInlineStyles)

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
    
    
## Documentation

The following properties exists and have set methods available:

Property | Default | Description
-------|---------|------------
cleanup|false|Should the generated HTML be cleaned?
useInlineStylesBlock |false|Use inline-styles block as CSS.
stripOriginalStyleTags |false|Strip original style tags.
excludeMediaQueries |true|Exclude media queries from extra "css" and keep media queries for inline-styles blocks.
excludeConditionalInlineStylesBlock |true|Exclude conditional inline-style blocks.


## Known issues

* no support for pseudo selectors
* UTF-8 charset is not always detected correctly. Make sure you set the charset to UTF-8 using the following meta-tag in the head: `<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />`. _(Note: using `<meta charset="UTF-8">` does NOT work!)_

## Sites using this class

* [Each site based on Fork CMS](http://www.fork-cms.com)
* [Print en Bind](http://www.printenbind.nl)
* [Tiki Wiki CMS Groupware](http://sourceforge.net/p/tikiwiki/code/49505) (starting in Tiki 13)
