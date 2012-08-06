<?php
/**
 * TwitterStore.php
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
 * TwitterSTore - uses the custom tree model components
 */
class TwitterStoreIter {

    /**
     * Only purpose is to keep track of our treeview iter madness
     *
     * @var int
     */
    public $index = 0;
}

/**
 * TwitterStore - uses the custom tree model components
 */
class TwitterStore extends PhpGtkCustomTreeModel {

    /**
     * total number of items to keep in the cached data before a rest
     *
     * @var int
     */
    const CACHE_LIMIT = 100;

    /**
     * Internal reference to the db class we're pulling data from
     *
     * @var Sqlite object
     */
    protected $database;

    /**
     * Internal reference to the db class we're pulling data from
     *
     * @var TwitterStoreIter object
     */
    protected $iter;

    /**
     * a cache of fetched rows from the db
     * we could do something fancy, but for now it just resets when
     * the limit is hit
     *
     * @var array
     */
    protected $cache;

    /**
     * keep of tally of number of items pushed into cache
     *
     * @var int
     */
    protected $cache_count = 0;

    /**
     * default pixbuf to use for all users ;)
     *
     * @var GdkPixbuf
     */
    protected $default_pixbuf;

    /**
     * current timeline we're hitting
     *
     * @var string
     */
    protected $timeline;

    /**
     * Current columns supported by this store
     *
     * @var array
     */
    protected $columns = array(GdkPixbuf::gtype,
                               GObject::TYPE_STRING,
                               GObject::TYPE_STRING,
                               GObject::TYPE_STRING);

    /**
     * method to retrieve gtk specific information about this store
     * the flag returned means it's a list, not a tree
     *
     * @return int
     */
    public function __construct(Sqlite $database, $timeline) {
        // set up internal data
        parent::__construct();
        $this->database = $database;
        $this->iter = new TwitterStoreIter;
        $this->timeline = $timeline;
        $this->default_pixbuf = GdkPixbuf::new_from_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp.png');
    }

    /**
     * method to retrieve gtk specific information about this store
     * the flag returned means it's a list, not a tree
     *
     * @return int
     */
    public function on_get_flags() {
        return Gtk::TREE_MODEL_LIST_ONLY;
    }

    /**
     * Tell the treeview that our store has a specific
     * number of columns
     *
     * @return int
     */
    public function on_get_n_columns() {
        return count($this->columns);
    }

    /**
     * Tell the treeview the types for each column
     *
     * @return int
     */
    public function on_get_column_type($index) {
        return $this->columns[$index];
    }

    /**
     * Retrieves an iter for our store
     * in this case the iter will be a row object
     * from pdo
     *
     * @return null|TwitterStoreIter
     */
    public function on_get_iter($path) {
        if (isset($path[0])) {
            $this->iter->index = $path[0];
            return $this->iter;
        }
        return null;
    }

    /**
     * Checks to see if we have more rows in the db
     * than the one we're on
     *
     * @return null|TwitterStoreIter
     */
    public function on_iter_next($iter) {
        $total = $this->database->count();
        if ($total > ($iter->index + 1)) {
            $this->iter->index++;
            return $this->iter;
        }
        return null;
    }

    /**
     * Checks to see if a specific
     *
     * @return null|TwitterStoreIter
     */
    public function on_iter_nth_child($iter, $n) {
        $total = $this->database->count($this->timeline);
        if ($total > ($n + 1)) {
            $this->iter->index = $n;
            return $this->iter;
        }
        return null;
    }

    /**
     * Grabs the data from the db and returns a specific column
     *
     * @return mixed
     */
    public function on_get_value($iter, $column) {
        // reset the cache if it's too damn big
        if ($this->cache_count > self::CACHE_LIMIT) {
            $this->cache = array();
            $this->cache_count = 0;
        }

        // our current row and row id
        $row = $iter->index;
        $id = $this->database->get_id_from_row($row, $this->timeline);
        if ($id == false) {
            return null;
        }

        // put data in the cache if needed
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->database->fetch($row, $this->timeline);
            $this->cache_count++;
        }

        // if they want 0 - they want the gdkpixbuf
        // so we need to check if the pic is downloaded, and if it is
        // create the pixbuf, otherwise we use the default pixbuf
        if ($column === 0) {
            return $this->default_pixbuf;
        }

        return $this->cache[$id][$column];
    }
}