<?php
/**
 * Api.php - \Callicore\Twitter\Api
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Api.php 21 2009-04-26 01:18:00Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   twitter
 * @filesource
 */

/**
 * Namespace for this application
 */
namespace Callicore\Twitter;

/**
 * This is a PHP streams based class for accessing the twitter API
 *
 * At some point it might be nice to have sockets, streams, curl options
 * but that might be overkill
 */
class Api {

    /**
     * stores current logged in status
     * @var boolean
     */
    protected $login = false;

    /**
     * Username in use for requests
     * @var string
     */
    protected $username;
    /**
     * Password in use for requests
     * @var string
     */
    protected $password;

    /**
     * Public timeline is cached to keep doing the request too often
     * @var array
     */
    protected $cached_public;
    /**
     * Timestamp from the last public timeline cache - so we know when to update
     * @var int
     */
    protected $cached_public_timestamp = 0;

    /**
     * Last id fetched, used when updating personal timelines
     * @var int
     */
    protected $lastid = 0;

    /**
     * Headers to send with the request
     * Header[0] is reserved for an "If-Modified-Since" addition and left blank
     * @var array of strings
     */
    protected $headers = array('',
                               'X-Twitter-Client: Twitter Callicore - PHP-GTK Twitter Client',
                               'X-Twitter-Client-Version: 0.1.0-dev',
                               'X-Twitter-Client-URL: http://callicore.net');

    /**
     * Checks login credentials for twitter, and stores username pass
     * if they are correct for future timeline fetches
     *
     * @param string $username twitter username
     * @param string $password twitter password
     * @return boolean
     */
    public function login($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $worked = $this->process('account/verify_credentials.json');
        if ($worked) {
            $this->login = true;
            return true;
        }

        // username and password are incorrect, unset them
        $this->username = null;
        $this->password = null;
        return false;
    }

    /**
     * Ends a current twitter session
     *
     * @return void
     */
    public function logout() {
        $this->username = null;
        $this->password = null;
        $this->login = false;
        $this->process('account/end_session', 0, 'POST');
    }

    /**
     * Grabs an array of information about the public timeline
     *
     * @return array
     */
    public function get_public_timeline() {
        if ($this->cached_public_timestamp < time()) {
            $this->cached_public = json_decode(file_get_contents('http://twitter.com/statuses/public_timeline.json'));
            $this->cached_public_timestamp = time() + 60; // caches every 60 seconds
        }
        return $this->cached_public;
    }

    /**
     * Grabs an array of information about the private timeline
     *
     * @return array
     */
    public function get_timeline() {
        if ($this->login && $this->can_call()) {
            if (empty($this->lastid)) {
                $data = $this->process('statuses/friends_timeline.json');
            } else {
                $data = $this->process('statuses/friends_timeline.json',
                                       $this->lasttime,
                                       'GET',
                                       array('since_id' => $this->lastid));
            }
            if ($data) {
                $this->lastid = $data[0]->id;
            }
            $this->lasttime = time();
            return $data;
        }
        return array();
    }

    /**
     * Sends a message to twitter
     *
     * @param string $message data to send
     * @return boolean
     */
    public function send($message) {
        if ($this->login && $this->can_call()) {
            $data = $this->process('statuses/update.json', 0, 'POST', array('status' => $message));
            if ($data) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks to see if twitter can be queried
     *
     * @return boolean
     */
    protected function can_call() {
        if (!$this->login) {
            return false;
        }
        $worked = $this->process('account/rate_limit_status.json');
        return ($worked->remaining_hits > 1);
    }

    /**
     * Main worker for the class, queries twitter and returns json information
     * which is then decoded
     *
     * @return array
     */
    protected function process($url, $date = 0, $type = 'GET', $data = null) {
        // add caching header
        $this->headers[0] = 'If-Modified-Since: ' . date(DATE_RFC822, $date);

        // set data into http stream context
        $options = array(
            'http' => array(
                'method' => $type,
                'header' => $this->headers)
            );
        if (!is_null($data)) {
            $options['http']['content'] = http_build_query($data);
        }
        $context = stream_context_create($options);

        // send username and password, or just plain URL
        if ($this->username && $this->password) {
            $base = 'http://' . urlencode($this->username) . ':' . urlencode($this->password)
            . '@twitter.com/';
        } else {
            $base = 'http://twitter.com/';
        }

        // stack on error swallower for file_get_contents
        set_error_handler(array($this,'swallow_error'));
        $string = file_get_contents($base . $url, false, $context);
        restore_error_handler();

        return json_decode($string);
    }

    /**
     * Actually a private function - DO NOT USE
     * This is a php error handler to swallow PHP streams errors
     *
     * @param int    $errno  error code
     * @param string $errstr error message
     * @return void
     */
    public function swallow_error($errno, $errstr) {}
}