<?php
/**
 * Client.php - \Callicore\Twitter\Client main window class
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Main.php 25 2009-04-30 23:58:52Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   lib
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use Callicore\Lib\Application; // for connecting the app quit to window close
use Callicore\Lib\Window; // for remember state functionality
use \Gtk; // we use constants from here
use \Gdk; // we use constants from here
use \GtkStatusbar; // need this for the window
use \GtkToolbar; // also need this
use \GtkToolButton; // more gtk classes
use \GtkEntry;
use \GtkLabel;
use GtkListStore;
use GtkVBox;
use GtkScrolledWindow;
use GtkTreeView;
use GtkTreeViewColumn;
use GtkCellRendererPixbuf;
use GtkCellRendererText;
use GdkPixbuf;
use Gobject;

class Main extends Window {

    protected $statusicon;
    protected $twitter;
    protected $treeview;
    protected $statusbar;

    protected $temp;
    protected $pic_queue = array();
    protected $pic_cached = array();

    protected $public_timeline_timeout;
    protected $load_images_timeout;

    public function __construct() {
        parent::__construct();
        $t = Application::getInstance()->translate;

        $this->set_icon_from_file(\Callicore\Lib\DIR. '/icons/logo.png');
        $this->set_size_request(300, 500);
        $this->set_title($t->_('Callicore Twitter'));
        $this->connect_simple('destroy', array(Application::getInstance(), 'quit'));

        // realize this to grab the gdkwindow, turn maximize off
        $this->realize();
        $this->window->set_decorations(Gdk::DECOR_BORDER | Gdk::DECOR_RESIZEH | Gdk::DECOR_TITLE | Gdk::DECOR_MENU | Gdk::DECOR_MINIMIZE);

        // Status Icon is only used if GtkStatusIcon exists
        if (class_exists('\GtkStatusIcon', false)) { // we use the absolute namespace name here, remember use autoloads
            $this->statusicon = new StatusIcon();
            $this->statusicon->connect('activate', array($this->statusicon,
            'activate_window'), $this);
            $this->set_skip_taskbar_hint(true);
            $this->connect('window-state-event', array($this, 'minimize_to_tray'));
        }

        $this->temp = sys_get_temp_dir() . 'php-gtk-twitter-api-cache\\';
        if (!file_exists($this->temp)) {
            mkdir($this->temp, null, true);
        }

        $this->twitter = new Api;
        $this->statusbar = new GtkStatusBar();

        // Create a toolbar with login button
        $tb = new GtkToolbar();
        $tb->set_show_arrow(false);
        $this->loginbutton = GtkToolButton::new_from_stock(Gtk::STOCK_JUMP_TO);
        $this->loginbutton->set_label('Login');
        $this->loginbutton->connect_simple('clicked', array($this, 'login'));
        $tb->insert($this->loginbutton, -1);

        // logout button, hide it
        $this->logoutbutton = GtkToolButton::new_from_stock(Gtk::STOCK_CLOSE);
        $this->logoutbutton->set_label('Logout');
        $this->logoutbutton->connect_simple('clicked', array($this, 'logout'));
        $tb->insert($this->logoutbutton, -1);
        $this->logoutbutton->set_sensitive(false);

        // Create an update area
        $this->updateentry = new GtkEntry();
        $this->updateentry->set_max_length(140);
        $this->updateentry->set_sensitive(false);
        $this->updateentry->connect('activate', array($this, 'send_update'));
        $this->entrystatus = new GtkLabel();

        // User image pixbuf, user image string, user name, user id, text, favorited, created_at, id
        $store = new GtkListStore(GdkPixbuf::gtype, Gobject::TYPE_STRING, Gobject::TYPE_STRING,
            Gobject::TYPE_LONG, GObject::TYPE_STRING, GObject::TYPE_BOOLEAN, GObject::TYPE_STRING,
            Gobject::TYPE_LONG);
        $store->set_sort_column_id(7, Gtk::SORT_DESCENDING);

        $list = $this->twitter->get_public_timeline();
        $this->statusbar->push(1, 'last updated ' . date('Y-m-d H:i') . ' - ' . count($list) . ' new tweets');

        // stuff the store
        foreach($list as $object) {
            $store->append(array(null, $object->user->profile_image_url, $object->user->name,
                $object->user->id, $object->text, $object->favorited, $object->created_at,
                $object->id));
        }

        $this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_public_timeline')); // every 60 seconds

        // stuff vbox
        $vbox = new GtkVBox();
        $this->add($vbox);
        $vbox->pack_start($tb, false, false);
        $scrolled = new GtkScrolledWindow();
        $scrolled->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_ALWAYS);
        $vbox->pack_start($scrolled);
        $this->treeview = new GtkTreeView($store);
        $scrolled->add($this->treeview);
        $this->treeview->set_property('headers-visible', false);
        $this->treeview->set_rules_hint(true);

        // This is temporary, for testing add the gtknotebook
        $this->notebook = new Notebook;
        $vbox->pack_start($this->notebook, false, false);
        $vbox->pack_start(new GtkLabel('What are you doing?'), false, false);
        $vbox->pack_start($this->updateentry, false, false);
        $vbox->pack_start($this->entrystatus, false, false);
        $vbox->pack_start($this->statusbar, false, false);

        $picture_renderer = new GtkCellRendererPixbuf();
        $picture_column = new GtkTreeViewColumn('Picture', $picture_renderer, 'pixbuf', 0);
        $picture_column->set_cell_data_func($picture_renderer, array($this, 'show_user'));
        $this->treeview->append_column($picture_column);

        $message_renderer = new GtkCellRendererText();
        $message_renderer->set_property('wrap-mode', Gtk::WRAP_WORD);
        $message_renderer->set_property('wrap-width', 200);
        $message_renderer->set_property('width', 10);

        $message_column = new GtkTreeViewColumn('Message', $message_renderer);
        $message_column->set_cell_data_func($message_renderer, array($this, 'message_markup'));
        $this->treeview->append_column($message_column);

        $this->treeview->set_resize_mode(Gtk::RESIZE_IMMEDIATE);
    }

    public function show_user($column, $cell, $store, $position) {
        $pic = $store->get_value($position, 1);
        $name = $this->temp . md5($pic);
        if (isset($this->pic_queue[$name])) {
            return;
        } elseif (isset($this->pic_cached[$name])) {
            $store = $this->treeview->get_model();
            if (is_null($store->get_value($position, 0))) {
                $pixbuf = GdkPixbuf::new_from_file($name . '.jpg');
                $store->set($position, 0, $pixbuf);
                $cell->set_property('pixbuf', $pixbuf);
            }
            return;
        }
        $this->pic_queue[$name] = array('name' => $name, 'url' => $pic,
            'pos' => $position, 'cell' => $cell);
        if (empty($this->load_images_timeout)) {
            $this->load_images_timeout = Gtk::timeout_add(500, array($this, 'pic_queue'));
        }
    }

    public function pic_queue() {
        $pic = array_shift($this->pic_queue);
        if (empty($pic)) {
            $this->load_images_timeout = null;
            return true;
        }
        if (!file_exists($pic['name'])) {
            file_put_contents($pic['name'] . '.jpg', file_get_contents($pic['url']));
            $this->pic_cached[$pic['name']] = $pic['url'];
        }
        return true; // keep the timeout going
    }

    public function message_markup($column, $cell, $store, $position) {
        $user = utf8_decode($store->get_value($position, 2));
        $message = utf8_decode($store->get_value($position, 4));
        $time = $this->distance($store->get_value($position, 6));

        $message = htmlspecialchars_decode($message, ENT_QUOTES);
        $message = str_replace(array('@' . $user, '&nbsp;', '&'), array('<span foreground="#FF6633">@' . $user . '</span>', ' ', '&amp;'), $message);
        $cell->set_property('markup', "<b>$user</b>:\n$message\n<small>$time</small>");
    }

    protected function distance($from) {
        $minutes = round(abs(time() - strtotime($from)) / 60);

        switch(true) {
            case ($minutes == 0):
                return 'less than 1 minute ago';
            case ($minutes < 1):
                return '1 minute ago';
            case ($minutes <= 55):
                return $minutes . ' minutes ago';
            case ($minutes <= 65):
                return 'about 1 hour ago';
            case ($minutes <= 1439):
                return 'about ' . round((float) $minutes / 60.0) . ' hours';
            case ($minutes <= 2879):
                return '1 day ago';
            default:
                return 'about ' . round((float) $minutes / 1440) . ' days ago';
        }
    }

    public function minimize_to_tray($window, $event) {
        if ($event->changed_mask == Gdk::WINDOW_STATE_ICONIFIED &&
            $event->new_window_state & Gdk::WINDOW_STATE_ICONIFIED) {
            $window->hide();
        }
        return true; //stop bubbling
    }

    public function update_public_timeline() {
        $this->pic_queue = array();
        $list = $this->twitter->get_public_timeline();
        $this->statusbar->pop(1);
        $this->statusbar->push(1, 'last updated ' . date('Y-m-d H:i') . ' - ' . count($list) . ' new tweets');
        $store = $this->treeview->get_model();
        $store->clear();
        foreach($list as $object) {
            $store->append(array(null, $object->user->profile_image_url, $object->user->name,
                $object->user->id, $object->text, $object->favorited, $object->created_at,
                $object->id));
        }
        return true;
    }

    public function update_timeline() {
        $list = $this->twitter->get_timeline();
        $this->statusbar->pop(1);
        $this->statusbar->push(1, 'last updated ' . date('Y-m-d H:i') . ' - ' . count($list) . ' new tweets');
        $store = $this->treeview->get_model();
        foreach($list as $object) {
            $store->append(array(null, $object->user->profile_image_url, $object->user->name,
                $object->user->id, $object->text, $object->favorited, $object->created_at,
                $object->id));
        }
        return true;
    }

    public function login() {
        if (!empty($this->load_images_timeout)) {
            Gtk::timeout_remove($this->load_images_timeout);
            $readd = true;
        }
        Gtk::timeout_remove($this->public_timeline_timeout);
        $login = new Login($this);
        while($response = $login->run()) {
            if ($response == GTK::RESPONSE_CANCEL || $response == GTK::RESPONSE_DELETE_EVENT) {
                if (isset($readd)) {
                    $this->load_images_timeout = Gtk::timeout_add(500, array($this, 'pic_queue'));
                }
                $this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_public_timeline')); // every 60 seconds
                $login->destroy();
                break;
            } elseif ($response == GTK::RESPONSE_OK) {
                if($login->check_login($this->twitter)) {
                    $this->logoutbutton->set_sensitive(true);
                    $this->loginbutton->set_sensitive(false);
                    $login->destroy();
                    $this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_timeline')); // every 60 seconds
                    $this->load_images_timeout = Gtk::timeout_add(500, array($this, 'pic_queue'));
                    $this->treeview->get_model()->clear();
                    $this->pic_queue = array();
                    $this->pic_cached = array();
                    $this->update_timeline();
                    $this->updateentry->set_sensitive(true);
                    break;
                }
            }
        }
    }

    public function logout() {
        $this->twitter->logout();
        $this->logoutbutton->set_sensitive(false);
        $this->loginbutton->set_sensitive(true);
        $this->public_timeline_timeout = Gtk::timeout_add(61000, array($this, 'update_public_timeline')); // every 60 seconds
        $this->pic_queue = array();
        $this->pic_cached = array();
        $this->update_public_timeline();
        $this->updateentry->set_sensitive(false);
    }

    public function send_update($entry) {
        if ($this->twitter->send($entry->get_text())) {
            $this->entrystatus->set_text('Message Sent');
            $this->update_timeline();
            $this->updateentry->set_text('');
        } else {
            $this->entrystatus->set_markup('<span color="red">Error Sending Message - Try Again</span>');
        }
    }

    public function __destruct() {
        foreach(scandir($this->temp) as $filename) {
            if ($filename[0] == '.')
            continue;
            if (file_exists($this->temp . $filename)) {
                unlink($this->temp . $filename);
            }
        }
    }
}