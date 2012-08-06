<?php
/**
 * Window.php
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://elizabethmariesmith.com/slides
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      0.2.0
 * @package      Php-Twit
 * @subpackage   lib
 * @filesource
 */

/**
 * Window - main window for application
 *
 * Contains a menu, statusbar, toolbar and gtknotebook
 */
class Window extends GtkWindow {

    /**
     * Internal vbox for window, holds the pieces
     *
     * @var GtkVBox object
     */
    public $vbox;

    /**
     * Api fun
     *
     * @var Api object
     */
    public $api;

    /**
     * db fun
     *
     * @var Sqlite object
     */
    public $database;

    /**
     * current db timeout int
     *
     * @var int
     */
    public $timeout;

    /**
     * Creates its own internal components
     *
     * @return void
     */
    public function __construct(array $settings, Sqlite $database, Api $api) {
        // always create the internal gtkwindow first
        parent::__construct();

        $this->set_size_request(300, 500);
        $this->set_title('PHP Twit');
        $this->connect_simple('destroy', array('Gtk', 'main_quit'));
        $this->connect_simple('show', array($this, 'on_show'));
        $this->api = $api;
        $this->database = $database;
        $this->timeline = 'home';

        $this->vbox = new GtkVBox();
        $this->add($this->vbox);

        $this->create_menu();
        // toolbar
        $this->create_notebook($database);
        // statusbar
        // hook statusbar messages into API transparently
    }

    /**
     * Creates a new gtkmenu and packs it
     *
     * @return void
     */
    public function create_menu() {
        $menu = new GtkMenuBar();

        /// sub items file and help
        $file = new GtkMenuItem('_File');
        $menu->append($file);

        $help = new GtkMenuItem('_Help');
        $menu->append($help);

        // File menu
        $file_menu = new GtkMenu();
        $file->set_submenu($file_menu);

        // Help menu
        $help_menu = new GtkMenu();
        $help->set_submenu($help_menu);

        // about sub item
        $about = new GtkImageMenuItem(Gtk::STOCK_ABOUT);
        $help_menu->append($about);
        $about->connect_simple('activate', array($this, 'about_dialog'));

        // settings sub item
        $settings = new GtkMenuItem('_Settings...');
        $file_menu->append($settings);
        $settings->connect_simple('activate', array($this, 'settings_dialog'));

        $file_menu->add(new GtkSeparatorMenuItem());

        // quit sub item
        $quit = new GtkImageMenuItem(Gtk::STOCK_QUIT);
        $file_menu->append($quit);
        $quit->connect_simple('activate', array($this, 'on_quit'));
        $this->vbox->pack_start($menu, false, false);
    }

    /**
     * Creates a new gtknotebook and packs it
     *
     * @return void
     */
    public function create_notebook() {
        $notebook = new GtkNotebook();
        

        // need to create the treeviews FIRST
        $this->friends_treeview = $this->create_treeview('home');
        $this->user_treeview = $this->create_treeview('user');
        $this->mentions_treeview = $this->create_treeview('mentions');

        $notebook->append_page($this->friends_treeview, new GtkLabel('Friends'));
        $notebook->append_page($this->user_treeview, new GtkLabel('Posted'));
        $notebook->append_page($this->mentions_treeview, new GtkLabel('Mentions'));

        $notebook->set_tab_pos(Gtk::POS_BOTTOM);

        $this->vbox->pack_start($notebook);
        $notebook->show_all();
        $notebook->connect('switch-page', array($this, 'on_switch_page'));
    }

    /**
     * Creates a timeline treeview
     *
     * @return void
     */
    public function create_treeview($timeline) {
        $scrolled = new GtkScrolledWindow();
        $scrolled->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_ALWAYS);

        $treeview = new Treeview($this->database, $timeline);
        $scrolled->add($treeview);
        return $scrolled;
    }

    /**
     * Creates the toolbar
     *
     * @return void
     */
    public function create_toolbar() {
        // toolbar items
        // refresh
        // online/offline toggle
        // box with who you're logged on as currently

        $this->vbox->pack_start($notebook, false, false);
    }

    /**
     * Don't hook up the timeouts until showtime
     *
     * @return void
     */
    public function on_show() {
        // can we login?
        if (!$this->timeout && $this->api->can_call()) {
            // set up timer for database cache update
            $update = 5 * 60 * 60; // 5 minutes in milliseconds
            $this->timeout = Gtk::timeout_add($update, array($this, 'update_timeline'));
        }
    }

    /**
     * Handles quitting the application
     *
     * @return void
     */
    public function on_quit() {
        // TODO: handle shutting down gracefully
        Gtk::main_quit();
    }

    /**
     * Timeout callback - grabs the last id in the db, hits the api
     * and stores data int the db
     *
     * @return void
     */
    public function update_timeline($continue = true) {
        $current_timeline = $this->timeline;
        if ($this->api->can_call()) {
            // TODO: deal with different timelines
            $last_id = $this->database->get_last_id($current_timeline);
            $data = $this->api->get_timeline($last_id, $current_timeline);
            foreach($data as $status) {
                $this->database->insert($status, $current_timeline);
                while(Gtk::events_pending()) {
                    Gtk::main_iteration();
                }
            }
        }

        // continue the timer
        return $continue;
    }

    /**
     * Handles about dialog
     *
     * @return void
     */
    public function on_switch_page($notebook, $pointer, $page_num) {
        if($page_num === 0) {
            $this->timeline = 'home';
        } elseif ($page_num === 1) {
            $this->timeline = 'user';
        } else {
            $this->timeline = 'mentions';
        }
    }

    /**
     * Handles settings dialog
     *
     * @return void
     */
    public function settings_dialog() {
        echo "settings dialog here";
    }

    /**
     * Handles about dialog
     *
     * @return void
     */
    public function about_dialog() {
        echo "about dialog here";
    }
}