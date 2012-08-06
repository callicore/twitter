<?php
/**
 * Twitter.php - \Callicore\Twitter base namespace
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Twitter.php 17 2009-04-25 21:30:35Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   lib
 * @filesource
 */

/**
 * Namespace for application is actually Callicore\Twitter
 */
namespace Callicore\Twitter;

/**
 * Current Twitter Application verion
 * @const string
 */
const VERSION = '0.1.0-dev';

/**
 * autoload implementation for application
 *
 * @param string $class class to include
 * @return bool
 */
function autoload($class) {
    // only Callicore\Twitter classes
    if (strncmp('Callicore\Twitter', $class, 13) !== 0) {
        return false;
    }

    // split on namespace and pop off callicore/twitter
    $array = explode('\\', $class);
    unset($array[0], $array[1]);

    // create partial filename
    $file = array_pop($array);
    // if we have no path left, add it back
    if (empty($array)) {
        $path = '';
    } else {
        $path = implode('/', $array);
    }
    $filename = __DIR__ . '/' . $path . '/' . $file . '.php';
    if (!file_exists($filename)) {
        trigger_error("File $filename could not be loaded", E_USER_WARNING);
        return false;
    }
    include $filename;
    return true;
}

/**
 * register the autoload
 */
\spl_autoload_register(__NAMESPACE__ . '\autoload');