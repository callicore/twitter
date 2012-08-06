<?php
/**
 * Treeview.php
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://elizabethmariesmith.com/slides
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      0.2.0
 * @package      Php-Twit
 * @subpackage   Lib
 * @filesource
 */

/**
 * Treeview - there are three treeviews, each with slightly different data in the store
 *
 * treeviews hold their own store information
 */
class Treeview extends GtkTreeview {

    /**
     * Creates its own internal components
     *
     * @return void
     */
    public function __construct(Sqlite $database, $timeline) {
        $store = new TwitterStore($database, $timeline);

        // always create the internal widget first
        parent::__construct();

        $this->set_resize_mode(Gtk::RESIZE_IMMEDIATE);
        $this->set_property('headers-visible', false);
        $this->set_rules_hint(true);
        $this->set_model($store);


        $picture_renderer = new GtkCellRendererPixbuf();
        $picture_column = new GtkTreeViewColumn('Picture', $picture_renderer, 'pixbuf', 0);
        $this->append_column($picture_column);

        $message_renderer = new GtkCellRendererText();
        $message_renderer->set_property('wrap-mode', Gtk::WRAP_WORD);
        $message_renderer->set_property('wrap-width', 200);
        $message_renderer->set_property('width', 10);

        $message_column = new GtkTreeViewColumn('Message', $message_renderer);
        $message_column->set_cell_data_func($message_renderer, array($this, 'message_markup'));
        $this->append_column($message_column);
    }

    /**
     * Formats several values from the twitterstore
     * into a nice string
     *
     * @return void
     */
    public function message_markup($column, $cell, $store, $position) {
        $user = utf8_decode($store->get_value($position, 1));
        $message = utf8_decode($store->get_value($position, 2));
        $time = $this->distance($store->get_value($position, 3));

        $message = htmlspecialchars_decode($message, ENT_QUOTES);
        $message = str_replace(array('@' . $user, '&nbsp;', '&'), array('<span foreground="#FF6633">@' . $user . '</span>', ' ', '&amp;'), $message);
        $cell->set_property('markup', "<b>$user</b>:\n$message\n<small>$time</small>");
    }

    /**
     * Little formatter for when the tweet happened ago
     *
     * @return void
     */
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
                return 'about ' . round((float) $minutes / 60.0) . ' hours ago';
            case ($minutes <= 2879):
                return '1 day ago';
            default:
                return 'about ' . round((float) $minutes / 1440) . ' days ago';
        }
    }
}