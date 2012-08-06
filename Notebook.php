<?php
/**
 * Notebook.php - \Callicore\Twitter\Notebook holds tabs for different views of timeline
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Notebook.php 25 2009-04-30 23:58:52Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   twitter
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use \Callicore\Lib\Application; // grab translate object
use \Gtk; // lots of gtk constants
use \GtkNotebook; // we extend this
use \GtkLabel; // we need labels for our tabs

class Notebook extends GtkNotebook {

    /**
     * The recent page
     * @var object instanceof something
     */
    protected $recent;

    /**
     * The replies page
     * @var object instanceof something
     */
    protected $replies;

    /**
     * The messages page
     * @var object instanceof something
     */
    protected $messages;

    /**
     * The everyone page
     * @var object instanceof something
     */
    protected $everyone;

    /**
     * The everyone page
     * @var array of GtkLabel
     */
    protected $labels = array('recent' => null,
                              'replies' => null,
                              'messages' => null,
                              'everyone' => null);

    /**
     * Creates 4 pages for the notebook and places tabs at the
     * bottom of the screen
     *
     * @param string $username twitter username
     * @param string $password twitter password
     * @return boolean
     */
    public function __construct() {
        // create the parent
        parent::__construct();

        // tabs go on the bottom
        $this->set_tab_pos(Gtk::POS_BOTTOM);

        // grab our translate object
        $t = Application::getInstance()->translate;

        // create our labels
        $this->labels['recent'] = new GtkLabel($t->_('recent'));
        $this->labels['replies'] = new GtkLabel($t->_('replies'));
        $this->labels['messages'] = new GtkLabel($t->_('messages'));
        $this->labels['everyone'] = new GtkLabel($t->_('everyone'));

        // create our notebook tabs
        $this->recent = new TweetList('recent');
        $this->replies = new TweetList('replies');
        $this->messages = new TweetList('messages');
        $this->everyone = new TweetList('everyone');

        // put our tabs in our notebook
        $this->append_page($this->recent, $this->labels['recent']);
        $this->append_page($this->replies, $this->labels['replies']);
        $this->append_page($this->messages, $this->labels['messages']);
        $this->append_page($this->everyone, $this->labels['everyone']);
    }
}