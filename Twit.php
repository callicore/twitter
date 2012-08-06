<?php
error_reporting(E_ALL | E_STRICT);

/**
 * Twit.php
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://elizabethmariesmith.com/slides
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      0.2.0
 * @package      Php-Twit
 * @subpackage   App
 * @filesource
 */

/**
 * Twit - application class
 *
 * Handles settings, creating main window or doing initial configuration
 * etc.
 */
class Twit {

    /**
     * absolute path to our configuration ini file
     *
     * @var string
     */
    public $configfile;

    /**
     * stored array of settings to write to or read from our config file
     *
     * @var array
     */
    public $settings = array('username' => 'auroraeosrose',
                             'password' => 'Iw1wqsdd');

    /**
     * Sets up the application and runs it, handles
     * configuration and generic methods
     *
     * @return void
     */
    public function __construct() {
        // autoload with default loader
        spl_autoload_register();

        // all our windows have the same icon
        GtkWindow::set_default_icon_from_file( __DIR__ . '/twitter.ico');

        // figure out where our configuration is
        $path = $this->get_app_dir();
        if (!file_exists($path)) {
            mkdir($path, 077, true);
        }
        $this->configfile = $path . DIRECTORY_SEPARATOR . 'config.ini';
        // try to load the ini_file, if it's garbage we don't care
        if (file_exists($this->configfile)) {
            $this->settings = parse_ini_file($this->configfile, true);
        }

        // create a new db instance - this will create a new db if one does not exist
        $database = new Sqlite($this->get_app_dir() . DIRECTORY_SEPARATOR . 'twitter.s3');

        // set up our api
        $api = $this->setup_api();

        // create our window, passing it our settings and database instance
        $this->window = new Window($this->settings, $database, $api);
        $this->window->show_all();
    }

    /**
     * sets up our api
     * 1. creates object
     * 2. if we have settings, logs in and sets up 5 minute refresh timer
     * 3. sets up the download queue for images
     *
     * @return void
     */
    public function setup_api() {
        // set up api client
        $api = new Api();
        // Set custom headers into the API client
        $api->set_headers(array('X-Twitter-Client' => 'PHP-Twit',
                                      'X-Twitter-Client-Version' => '0.2.0-dev',
                                      'X-Twitter-Client-URL' => 'http://elizabethmariesmith.com'));

        if (isset($this->settings['username']) && isset($this->settings['password'])) {
            $api->login($this->settings['username'], $this->settings['password']);
        }

        return $api;
    }

    /**
     * Write out any settings - do this on shutdown
     */
    public function write_settings() {
                // create ini file
        $string = '; Preferences and Configuration for PHP Twit' . PHP_EOL
                . '; Saved ' . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

        foreach ($this->settings as $key => $value) {
            if (is_bool($value)) {
                $string .= preg_replace('/[' . preg_quote('{}|&~![()"') . ']/', '', $key)
                    . ' = ' . (($value == true) ? 'TRUE' : 'FALSE') . PHP_EOL;
            } elseif (is_scalar($value)) {
                $string .= preg_replace('/[' . preg_quote('{}|&~![()"') . ']/', '', $key)
                    . ' = "' . str_replace('"', '', $value) . '"' . PHP_EOL;
            } else {
                foreach ($value as $var)
                {
                    if(!is_scalar($var)) {
                        trigger_error('Data can only nest arrays two deep due to limitations in ini files, item not written', E_USER_NOTICE);
                    }
                    $string .= preg_replace('/[' . preg_quote('{}|&~![()"') . ']/', '', $key) . '[] = "' . str_replace('"', '', $var) . '"' . PHP_EOL;
                }
            }
        }

        file_put_contents($this->configfile, $string);
    }

    /**
     * fetches proper application data locations on different systems
     */
    public function get_app_dir() {
        if (isset($_ENV['APPDATA'])) {
            $home = $_ENV['APPDATA'] . DIRECTORY_SEPARATOR;
        } elseif (isset($_ENV['HOME'])) {
            $home = $_ENV['HOME'] . DIRECTORY_SEPARATOR;
        } else {
            $home = dirname(__FILE__);
        }
        if (stristr(PHP_OS, 'win')) {
            return $home . 'php-twit' . DIRECTORY_SEPARATOR;
        } elseif (stristr(PHP_OS, 'darwin') || stristr(PHP_OS, 'mac')) {
            return $home . 'Library/Application Support/php-twit' . DIRECTORY_SEPARATOR;
        } else {
            return $home . '.php-twit' . DIRECTORY_SEPARATOR;
        }
    }
}

new Twit();
Gtk::main();