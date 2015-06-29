<?php
if (is_file(dirname(__DIR__) . '/src/voku/CssToInlineStyles.php')) {
  require_once dirname(__DIR__) . '/src/voku/CssToInlineStyles.php';
  require_once dirname(__DIR__) . '/src/voku/Exception.php';
  require_once dirname(__DIR__) . '/src/voku/Specificity.php';
} else {
  require_once dirname(__DIR__) . '/vendor/autoload.php';
}