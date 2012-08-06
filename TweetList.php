<?php
/**
 * TweetList.php - \Callicore\Twitter\TweetList gtktreeview + gtkmodel for listing tweets
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: TweetList.php 25 2009-04-30 23:58:52Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   twitter
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use \Gtk; // a lot of constants from here
use \Gobject; // type constants for liststore
use \GtkScrolledWindow; // this is the base widget we're using
use \GtkListStore; // our data store
use \GtkTreeView; // viewing the store
use \GtkCellRendererText; // view text
use \GtkTreeViewColumn; // putting renderer in a column

class TweetList extends GtkScrolledWindow {

    /**
     * The treeview widget
     * @var object instanceof GtkTreeView
     */
    protected $treeview;

    /**
     * The liststore widget
     * @var object instanceof GtkListStore
     */
    protected $liststore;

    /**
     * Creates a listview inside a treeview inside a gtkscrolledwindow
     * the data can be one of recent, replies, messages, and everyone
     * and is retreived from the twitter API
     *
     * @return void
     */
    public function __construct($type) {
        // create the parent
        parent::__construct();
        // we only scroll vertically please
        $this->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_ALWAYS);

        // create our liststore to put the twitter data in
        $store = new GtkListStore(GObject::TYPE_STRING);
        $store->append(array($type));

        // create the treeview, set some settings, and add it
        $this->treeview = new GtkTreeView($store);
        $this->treeview->set_property('headers-visible', false);
        $this->treeview->set_rules_hint(true);
        $this->treeview->set_resize_mode(Gtk::RESIZE_IMMEDIATE);
        $this->add($this->treeview);

        $renderer = new GtkCellRendererText();

        $column = new GtkTreeViewColumn('Message', $renderer, 'text', 0);
        $this->treeview->append_column($column);
    }
}