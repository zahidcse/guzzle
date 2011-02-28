<?php
/**
 * @package Guzzle PHP <http://www.guzzlephp.org>
 * @license See the LICENSE file that was distributed with this source code.
 *
 * This file bootstraps the test environment.
 */

namespace Guzzle\Tests;

error_reporting(E_ALL | E_STRICT);

require_once 'PHPUnit/TextUI/TestRunner.php';
require_once __DIR__ . '/../../../library/vendor/Symfony/Framework/UniversalClassLoader.php';

$classLoader = new \Symfony\Framework\UniversalClassLoader();
$classLoader->registerNamespaces(array(
    'Guzzle\Tests' => __DIR__ . '/../../../tests',
    'Guzzle' => __DIR__ . '/../../../library',
    'Doctrine' => __DIR__ . '/../../../library/vendor'
));

$classLoader->registerPrefix('Zend_',  __DIR__ . '/../../../library/vendor');
$classLoader->register();

set_include_path(
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'library'
    . PATH_SEPARATOR .
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
    . PATH_SEPARATOR .
    get_include_path()
);