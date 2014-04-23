<?php

  define('SF_ROOT_DIR', realpath(dirname(__FILE__) . '/..'));
  define('SF_APP', 'frontend');
  define('SF_ENVIRONMENT', 'test-empty');
  define('SF_DEBUG', TRUE);

  /** @noinspection PhpIncludeInspection */
  require_once(SF_ROOT_DIR . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . SF_APP . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

  sfContext::getInstance()->getController()->dispatch();