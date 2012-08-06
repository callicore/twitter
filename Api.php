<?php
/**
 * Api.php
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
 * Api - very simple wrapper for twitter API
 *
 * Uses PHP streams to make REST requests
 * only does authenticated requests
 *
 * gets friends, user and mention timeline
 * does posting simple new tweets
 */
class Api {

    /**
     * Username for authentication
     *
     * @var string
     */
    protected $username;

    /**
     * password for authentication
     *
     * @var string
     */
    protected $password;

    /**
     * last response code
     *
     * @var int
     */
    protected $lastresponse;

    /**
     * current rate limit in effect
     *
     * @var int
     */
    protected $ratelimit;

    /**
     * rate limit number remaining
     *
     * @var int
     */
    protected $rateremaining;

    /**
     * rate reset time as unix timestamp (epoch)
     *
     * @var int
     */
    protected $ratereset;

    /**
     * default headers to send with each request
     *
     * @var array()
     */
    protected $headers;

    /**
     * last timestamp that hometimeline was hit
     *
     * @var int
     */
    protected $lasthometimeline;

    /**
     * last timestamp that usertimeline was hit
     *
     * @var int
     */
    protected $lastusertimeline;

    /**
     * last timestamp that mentiontimeline was hit
     *
     * @var int
     */
    protected $lastmentionstimeline;

    /**
     * verifies the username and password
     * then stores them in the class
     *
     * @param string $username
     * @param string $password
     * @return boolean
     */
    public function login($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $worked = $this->process('account/verify_credentials.json');
        if ($worked && $this->lastresponse == 200) {
            return true;
        }
        $this->username = null;
        $this->password = null;
        return false;
    }

    /**
     * Allows you to set custom headers to send with the request
     * headers must be in $headername => $headervalue format
     * will overwrite any currently set headers
     *
     * @param array $headers
     * @return void
     */
    public function set_headers(array $headers) {
        $this->headers = array();
        foreach($headers as $name => $value) {
            $this->headers[] = $name . ': ' . $value;
        }
    }

    /**
     * Grabs the default timeline, or all the posts since $last_id
     *
     * @param int $last_id get any tweets earlier than the last id
     * @return array|false
     */
    public function get_timeline($timeline, $last_id = null) {
        switch($timeline) {
            case 'mentions':
                 $url = 'mentions';
                 break;
            case 'user':
                $url = 'user_timeline';
                break;
            default:
                $url = 'friends_timeline';
                $timeline = 'home';
        }
        if ($this->can_call()) {
            if (is_null($last_id)) {
                 $data = $this->process('statuses/' . $url . '.json');
            } else {
                $data = $this->process('statuses/' . $url . '.json', $this->{'last' . $timeline . 'timeline'}, 'GET', array('since_id' => $last_id));
            }
            $this->{'last' . $timeline . 'timeline'} = time();
            return $data;
        }
        return false;
    }

    /**
     * Posts a new message
     *
     * @param string $message text of message to post
     * @return boolean
     */
    public function send($message) {
        if ($this->can_call()) {
            $data = $this->process('statuses/update.json', 0, 'POST', array('status' => $message));
            if ($data) {
                return true;
            }
            return false;
        }
    }

    /**
     * Can we do the api call, checks that we haven't hit the rate limit,
     * if we have hit the rate limit then we haven't hit the reset time,
     * and checks to see if we're logged in
     *
     * @return boolean
     */
    public function can_call() {
       if ($this->username && $this->password &&
           (is_null($this->ratelimit) || ($this->rateremaining > $this->ratelimit
            || $this->ratereset != time()))) {
            return true;
        }
        return false;
    }

    /**
     * Actually does the http request to the twitter site
     *
     * @param string $url api method we want to hit
     * @param int $date date of last call to url api method
     * @param string $type GET or POST
     * @param array|null $data data to send with post or get
     * @return mixed
     */
    protected function process($url, $date = 0, $type = 'GET', $data = null) {
        $headers = $this->headers;

        // add caching header
        $headers[] = 'If-Modified-Since: ' . date(DATE_RFC822, $date);
        $headers[] = 'Content-type: text/plain';

        // set up options for the request
        $options = array(
            'http' => array(
                'method' => $type,
                'header' => $headers)
        );

        if (!is_null($data)) {
            $options['http']['content'] = http_build_query($data);
        }

        $context = stream_context_create($options);
        if ($this->username && $this->password) {
             $base = 'http://' . urlencode($this->username) . ':' . urlencode($this->password)
                    . '@twitter.com/';
        } else {
            $base = 'http://twitter.com/';
        }

        $this->lastresponse = null;
        //set_error_handler(array($this,'swallow_error'));
        $string = file_get_contents($base . $url, false, $context);
        //restore_error_handler();

        // get our response status and store it
        sscanf($http_response_header[0], 'HTTP/%s %s', $http_version, $returncode);
        $this->lastresponse = $returncode;

        // parse out rate response headers
        foreach($http_response_header as $value) {
            if (strpos($value, 'X-RateLimit') === 0) {
                $data = explode(':', $value, 2);
                $data = array_map('trim', $data);
                if ($data[0] === 'X-RateLimit-Limit') {
                    $this->ratelimit = intval($data[1]);
                } elseif ($data[0] === 'X-RateLimit-Remaining') {
                    $this->rateremaining = intval($data[1]);
                } elseif ($data[0] === 'X-RateLimit-Reset') {
                    $this->ratereset = intval($data[1]);
                }
            }
        }

        return json_decode($string);
    }

    /**
     * Swallows all errors from file_get_contents
     * should be handled more elegantly at some point
     *
     * @access private
     * @param int $errno
     * @param string $errstr
     * @return void
     */
    public function swallow_error($errno, $errstr) {} // this should be treated as private
}