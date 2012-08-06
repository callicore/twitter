<?php
/**
 * Client.php - \Callicore\Twitter\Client actual application class
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Client.php 25 2009-04-30 23:58:52Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   lib
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use \Callicore\Lib\Application; // this should have been loaded in the bootstrap

/**
 * actual application class, handles startup, shutdown, etc
 */
class Client extends Application {

    /**
     * Our application name, overrides Application default
     *
     * @var string
     */
    protected $name = 'Twitter';

    /**
     * Startup method for the twitter application
     *
     * @return void
     */
    public function main(){
        // If we don't have settings yet, run the startup wizard
        if (!isset($this->config['accounts'])) {
            $wizard = new Setup();
            $wizard->show_all();
        } else {
            $window = new Main();
            $window->show_all();
        }
    }
}