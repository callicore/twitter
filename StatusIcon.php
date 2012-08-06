<?php
/**
 * StatusIcon.php - \Callicore\Twitter\StatusIcon statusicon class
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: StatusIcon.php 21 2009-04-26 01:18:00Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   lib
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use \Gtk; // we use some GTK methods
use \Gdk; // we use event constants
use \GtkStatusIcon; // class we're extending

class StatusIcon extends GtkStatusIcon {

    protected $alive;
    protected $lockout;

    public function __construct() {
        parent::__construct();

        $this->set_from_stock(Gtk::STOCK_ABOUT);
        $this->set_tooltip('PHP-GTK Twitter Client');
        while(Gtk::events_pending() || Gdk::events_pending()) {
            Gtk::main_iteration_do(true);
        }
        $this->is_ready();
        return;
    }

    public function is_ready() {
        $this->alive = true;
        if($this->lockout < 5 && !$this->is_embedded()) {
            Gtk::timeout_add(750,array($this,'is_ready'));
            ++$this->lockout;
            $this->alive = false;
        } else if(!$this->is_embedded()) {
            trigger_error('Error: Unable to create Tray Icon. Please insure that your system\'s tray is enabled.');
            $this->alive = false;
        }

        return;
    }

    public function activate_window($icon, $window) {
        if ($window->is_visible()) {
            $window->hide();
        } else {
            $window->deiconify();
            $window->show();
        }
    }
}