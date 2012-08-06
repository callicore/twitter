<?php
/**
 * Sqlite.php
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
 * Sqlite - generic db class for storing twitter data in sqlite db
 *
 * Has ddl inside, will create db if it doesn't exist
 */
class Sqlite extends PDO {

    /**
     * Fetch statement, we'll leave it lying around
     *
     * @var PdoStatement object
     */
    protected $fetch_stmt;

    /**
     * Count statement, we'll leave it lying around
     *
     * @var PdoStatement object
     */
    protected $count_stmt;

    /**
     * get id statement, we'll leave it lying around
     *
     * @var PdoStatement object
     */
    protected $get_id_stmt;

    /**
     * Definition for data in database
     *
     * @var array
     */
    protected $ddl = array('status' => 'CREATE TABLE status(id INTEGER PRIMARY KEY,
                                         in_reply_to_user_id INTEGER,
                                         text TEXT,
                                         in_reply_to_screen_name TEXT,
                                         created_at TEXT,
                                         time INTEGER,
                                         truncated INTEGER,
                                         source TEXT,
                                         in_reply_to_status_id INTEGER,
                                         favorited INTEGER,
                                         user_id INTEGER);',
                           'user' => 'CREATE TABLE user (id INTEGER PRIMARY KEY,
                                         created_at TEXT,
                                         followers_count INTEGER,
                                         screen_name TEXT,
                                         friends_count INTEGER,
                                         favourites_count INTEGER,
                                         location TEXT,
                                         time_zone TEXT,
                                         protected INTEGER,
                                         name TEXT,
                                         statuses_count INTEGER,
                                         url TEXT,
                                         following INTEGER,
                                         notifications INTEGER,
                                         description TEXT,
                                         profile_image_url TEXT,
                                         profile_image TEXT);',
                           'timeline' => 'CREATE TABLE timeline (timeline TEXT,
                                             status_id INTEGER);',
                           'image_queue' => 'CREATE TABLE image_queue (user_id TEXT,
                                             profile_image_url TEXT,
                                             time INTEGER);');

    /**
     * Creates a new sqlite file database, puts the ddl in if necessary
     *
     * @param string $file absolute path to file for sqlite db
     * @return void
     */
    public function __construct($file) {
        parent::__construct('sqlite:' . $file);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

        // see if the ddl is created appropriately
        $tables = array_flip(array_keys($this->ddl));
        $query = $this->query('SELECT name FROM sqlite_master WHERE type=\'table\'', PDO::FETCH_COLUMN, 0);

        if ($query === false) {
            $query = array();
        }

        foreach($query as $tablename) {
            if (isset($tables[$tablename])) {
                unset($tables[$tablename]);
            }
        }
        unset($query, $tablename);

        // do ddl for each table that does not exist
        foreach($tables as $name => $value) {
            $this->exec($this->ddl[$name]);
        }
    }

    /**
     * Grabs the last id fetched cronologically from a specific timeline
     *
     * @param string $file absolute path to file for sqlite db
     * @return void
     */
    public function get_last_id($timeline = 'home') {
        switch($timeline) {
            case 'home':
            case 'user':
            case 'mention':
                break;
            default:
                $timeline = 'home';
        }

        $query = $this->query('SELECT id FROM status
                                  INNER JOIN timeline ON status_id=id
                                  ORDER BY time DESC LIMIT 1');
        return $query->fetchColumn();
    }

    /**
     * Inserts a status in to the db
     *
     * @param string $timeline valid options are home, mention, or user
     * @return void
     */
    public function insert($data, $timeline = 'home') {
        // if data is empty, return
        if (empty($data)) {
            return;
        }

        // is the status in the status table
        $stmt = $this->prepare('SELECT id FROM status WHERE id=:id');
        $stmt->execute(array('id' => $data->id));
        if(!$stmt->fetchColumn()) {
             $stmt = $this->prepare(
                'INSERT INTO status (id,
                                     in_reply_to_user_id,
                                     text,
                                     in_reply_to_screen_name,
                                     created_at,
                                     time,
                                     truncated,
                                     source,
                                     in_reply_to_status_id,
                                     favorited,
                                     user_id)
                            VALUES (:id,
                                    :in_reply_to_user_id,
                                    :text,
                                    :in_reply_to_screen_name,
                                    :created_at,
                                    :time,
                                    :truncated,
                                    :source,
                                    :in_reply_to_status_id,
                                    :favorited,
                                    :user_id)');
            $stmt->execute(array('id' => $data->id,
                                 'in_reply_to_user_id' => $data->in_reply_to_user_id,
                                 'text' => $data->text,
                                 'in_reply_to_screen_name' => $data->in_reply_to_screen_name,
                                 'created_at' => $data->created_at,
                                 'time' => strtotime($data->created_at),
                                 'truncated' => $data->truncated,
                                 'source' => $data->source,
                                 'in_reply_to_status_id' => $data->in_reply_to_status_id,
                                 'favorited' => $data->favorited,
                                 'user_id' => $data->user->id));
        }

        // is the status in the timeline table
        $stmt = $this->prepare('SELECT status_id FROM timeline WHERE status_id=:id AND timeline=:timeline');
        $stmt->execute(array('id' => $data->id, 'timeline' => $timeline));
        if(!$stmt->fetchColumn()) {
             $stmt = $this->prepare(
                'INSERT INTO timeline (status_id,
                                     timeline)
                            VALUES (:id,
                                    :timeline)');
            $stmt->execute(array('id' => $data->id, 'timeline' => $timeline));
        }

        // is the user in the user table, or is the user updated
        $stmt = $this->prepare('SELECT * FROM user WHERE id=:id');
        $stmt->execute(array('id' => $data->user->id));
        $row = $stmt->fetchObject();
        $update = false;
        if ($row) {
            foreach($row as $key => $value) {
                if(isset($data->$key) && $row->$key != $data->$key) {
                    $update = true;
                    break;
                }
            }
        }
        if (!$row || $update == true) {
            $stmt = $this->prepare(
                  'REPLACE INTO user (id,
                                     created_at,
                                     followers_count,
                                     screen_name,
                                     friends_count,
                                     favourites_count,
                                     location,
                                     time_zone,
                                     protected,
                                     name,
                                     statuses_count,
                                     url,
                                     following,
                                     notifications,
                                     description,
                                     profile_image_url,
                                     profile_image)
                            VALUES (:id,
                                    :created_at,
                                    :followers_count,
                                    :screen_name,
                                    :friends_count,
                                    :favourites_count,
                                    :location,
                                    :time_zone,
                                    :protected,
                                    :name,
                                    :statuses_count,
                                    :url,
                                    :following,
                                    :notifications,
                                    :description,
                                    :profile_image_url,
                                    :profile_image)');
            $stmt->execute(array('id' => $data->user->id,
                                 'created_at' => $data->user->created_at,
                                 'followers_count' => $data->user->followers_count,
                                 'screen_name' => $data->user->screen_name,
                                 'friends_count' => $data->user->friends_count,
                                 'favourites_count' => $data->user->favourites_count,
                                 'location' => $data->user->location,
                                 'time_zone' => $data->user->time_zone,
                                 'protected' => $data->user->protected,
                                 'name' => $data->user->name,
                                 'statuses_count' => $data->user->statuses_count,
                                 'url' => $data->user->url,
                                 'following' => $data->user->following,
                                 'notifications' => $data->user->notifications,
                                 'description' => $data->user->description,
                                 'profile_image_url' => $data->user->profile_image_url,
                                 'profile_image' => null));

        }
    }

    /**
     * Fetches a specific row from the db
     *
     * @param int $index specific row we are looking for when ordered by time
     * @param string $timeline valid options are home, mention, or user
     * @return void
     */
    public function fetch($index, $timeline = 'home') {
        $limit = $index + 1;
        $offset = $index;
        if (!$this->fetch_stmt) {
            $this->fetch_stmt = $this->prepare('SELECT profile_image_url, user.screen_name, status.text, status.created_at FROM status
                                                INNER JOIN timeline ON timeline.status_id=status.id
                                                INNER JOIN user ON status.user_id=user.id
                                                WHERE timeline=:timeline
                                                ORDER BY time DESC LIMIT :limit OFFSET :offset');
        }
        $this->fetch_stmt->execute(array('limit' => $limit, 'offset' => $offset, 'timeline' => $timeline));
        return $this->fetch_stmt->fetch(PDO::FETCH_NUM);
    }

    /**
     * Counts the current number of rows of data in a specific timeline
     *
     * @param string $timeline valid options are home, mention, or user
     * @return void
     */
    public function count($timeline = 'home') {
        if (!$this->count_stmt) {
            $this->count_stmt = $this->prepare('SELECT COUNT(*) FROM timeline
                                                WHERE timeline=:timeline');
        }
        $this->count_stmt->execute(array('timeline' => $timeline));
        return $this->count_stmt->fetchColumn();
    }

    /**
     * Grabs the status id in a specific location in the timeline
     *
     * @param string $timeline valid options are home, mention, or user
     * @return void
     */
    public function get_id_from_row($index, $timeline = 'home') {
        $limit = $index + 1;
        $offset = $index;
        if (!$this->get_id_stmt) {
            $this->get_id_stmt = $this->prepare('SELECT id FROM status
                                                INNER JOIN timeline ON status_id=status.id
                                                WHERE timeline=:timeline
                                                ORDER BY time DESC LIMIT :limit OFFSET :offset');
        }
        $this->get_id_stmt->execute(array('limit' => $limit, 'offset' => $offset, 'timeline' => $timeline));
        return $this->get_id_stmt->fetchColumn();
    }
}
