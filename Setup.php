<?php
/**
 * Setup.php - \Callicore\Twitter\Setup gtkassistant (wizard) for inital setup
 *
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Setup.php 25 2009-04-30 23:58:52Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   twitter
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use Callicore\Lib\Application; // fetch our translation stuff
use \GtkAssistant; // a lot of constants from here
use \GtkVBox; // for pages
use \GtkHBox;
use \Gdk; // constants madness
use \Gtk; // constants madness
use \GtkLabel; // for text inside pages
use \GtkLinkButton; // for urls
use \GtkEntry; // for login details
use \GtkTable; // nice layout for login form
use \GtkButton; // validation for checking login
use \GtkComboBox; // make pretty drop downs

class Setup extends GtkAssistant {

    /**
     * A cached twitter API instance for login checks
     * @var object instanceof Api
     */
    protected $twitter;

    /**
     * Username/email that has been verified
     * @var string
     */
    protected $email;

    /**
     * Password that has been verified
     * @var string
     */
    protected $password;

    /**
     * Stored pages
     * @var object instanceof GtkWidget
     */
    protected $pages;

    /**
     * Our main_quit handler must be disconnected before we start up the main window
     * @var integer
     */
    protected $id;

    /**
     * Labels for page 2
     * @var array of GtkEntries
     */
    protected $login = array('password' => null,
                              'email' => null,
                              'error' => null);

    /**
     * A step by step wizard to run the first time the program
     * starts to set up accounts and settings
     *
     * @return void
     */
    public function __construct() {
        // create the parent
        parent::__construct();

        // grab our translate object
        $t = Application::getInstance()->translate;

        $this->set_icon_from_file(\Callicore\Lib\DIR. '/icons/logo.png');
        $this->set_size_request(450, 300);
        $this->set_title($t->_('Callicore Twitter Setup'));
        $this->set_position(Gtk::WIN_POS_CENTER);

        // realize this to grab the gdkwindow, turn maximize off
        // maximizing a wizard makes little sense
        $this->realize();
        $this->window->set_decorations(Gdk::DECOR_BORDER | Gdk::DECOR_RESIZEH | Gdk::DECOR_TITLE | Gdk::DECOR_MENU | Gdk::DECOR_MINIMIZE);

        // destroy does not happen automagically when you click that close button, so we force it
        $this->connect_simple('delete_event', array($this, 'destroy'));
        // cancel is the same as destroy
        $this->connect_simple('cancel', array($this, 'destroy'));
        // destroying this should shut down the application completely, it MUST be run
        $this->id = $this->connect_simple('destroy', array(Application::getInstance(), 'quit'));

        // pages
        // 1. Welcome and what this is
        $this->pages[1] = new GtkVBox();
        $this->pages[1]->add($label = new GtkLabel($t->_(
            'This will guide you through setting up a default twitter account and choosing your settings for Callicore Twitter.'
            . PHP_EOL . PHP_EOL . 'You must have a valid twitter account to use this client.  Please register if you do not.'
            . PHP_EOL . PHP_EOL . 'Click Forward to begin.')));
        $label->set_line_wrap(true);

        // twitter website button that execs out to browser
        $button = new GtkLinkButton('https://twitter.com/signup', $t->_('Register at Twitter'));
        $hbox = new GtkHBox();
        $hbox->pack_start($button, true, false);
        $this->pages[1]->pack_end($hbox, false, false);
        GtkLinkButton::set_uri_hook(array('\Callicore\Lib\Util', 'uri_hook')); // otherwise it uses gtk_show_uri()

        // Put the page in the wizard and mark it complete
        $this->append_page($this->pages[1]);
        $this->set_page_title($this->pages[1], $t->_('Welcome to the Callicore Twitter Setup Wizard'));
        $this->set_page_type($this->pages[1], GTK::ASSISTANT_PAGE_INTRO);
        $this->set_page_complete($this->pages[1], true);

        // 2. Create account page (with check auth button)
        $this->pages[2] = new GtkVBox();
        $this->pages[2]->add($label = new GtkLabel($t->_(
            'Enter your twitter email/username and your password, then click "Verify Account".'
            . PHP_EOL . PHP_EOL . 'If the account is valid, you may continue to settings.')));
        $label->set_line_wrap(true);
        $this->append_page($this->pages[2]);
        $this->set_page_title($this->pages[2], $t->_('Create your Account'));
        $this->set_page_type($this->pages[2], GTK::ASSISTANT_PAGE_CONTENT);

        // create a table, store email and password entries to use later
        $table = new GtkTable();
        $email = new GtkLabel('Email:');
        $table->attach($email, 0, 1, 0, 1);
        $password = new GtkLabel('Password:');
        $table->attach($password, 0, 1, 1, 2);
        $this->login['email'] = new GtkEntry();
        $table->attach($this->login['email'], 1, 2, 0, 1);
        $this->login['password'] = new GtkEntry();
        $table->attach($this->login['password'], 1, 2, 1, 2);
        $this->login['password']->set_visibility(false);
        $this->pages[2]->add($table);
        $this->login['error'] = new GtkLabel();
        $this->pages[2]->add($this->login['error']);
        $button = new GtkButton($t->_('_Verify Account'), true);
        $hbox = new GtkHBox();
        $hbox->pack_start($button, true, false);
        $this->pages[2]->pack_end($hbox, false, false);
        $button->connect_simple('clicked', array($this, 'check_login'));

        // 3. Settings page (always completed)
        $this->pages[3] = new GtkVBox();
        $this->pages[3]->add($label = new GtkLabel($t->_(
            'Choose your settings and hit forward to continue.')));
        $this->append_page($this->pages[3]);
        $this->set_page_title($this->pages[3], $t->_('Client Settings'));
        $this->set_page_type($this->pages[3], GTK::ASSISTANT_PAGE_CONTENT);

        $table = new GtkTable();
        $combo_label = new GtkLabel('Check for tweets every :');
        $table->attach($combo_label, 0, 1, 0, 1);
        $sound_label = new GtkLabel('Beep on new tweets :');
        $table->attach($sound_label, 0, 1, 1, 2);
        $statusicon_label = new GtkLabel('Put icon in tray :');
        $table->attach($statusicon_label, 0, 1, 2, 3);
        $minimize_label = new GtkLabel('Minimize to tray :');
        $table->attach($minimize_label, 0, 1, 3, 4);
        $popup_label = new GtkLabel('Display popups :');
        $table->attach($popup_label, 0, 1, 4, 5);
        $popup_duration = new GtkLabel('Popup Duration :');
        $table->attach($popup_duration, 0, 1, 5, 6);

        // get tweets interval
        $combo = GtkComboBox::new_text();
        $combo->append_text('5 minutes');
        $combo->append_text('10 minutes');
        $combo->append_text('30 minutes');
        $table->attach($combo, 1, 2, 0, 1);

        $this->pages[3]->add($table);


        // Combo box for tweet fetch intervals
        // get tweets ever (3, 5, 7, 9) minutes
        // 1. Use statusicon
        // 2. minimize to statusicon
        // 3. display popups
        // 4. popup interval
        // 5. play sound

        // 4. Save and run real client
        $this->pages[4] = new GtkVBox();
        $this->append_page($this->pages[4]);
        $this->set_page_title($this->pages[4], $t->_('Save Settings and Launch Client'));
        $this->set_page_type($this->pages[4], GTK::ASSISTANT_PAGE_CONFIRM);
        $this->set_page_complete($this->pages[4], true);
        $this->connect_simple('apply', array($this, 'save'));
    }

    /**
     * Checks the login values, if they're good, completed is set to true for
     * page 2 and the values are put into config, if they're not good an error
     * message is provided
     *
     * @return void
     */
    public function check_login() {
        $error = $this->login['error'];
        $error->set_text('');
        $email = $this->login['email']->get_text();
        $password = $this->login['password']->get_text();
        if (empty($password) || empty($password)) {
            $t = Application::getInstance()->translate;
            $error->set_markup(sprintf('<span color="red">%s</span>', $t->_('Name and Password must be entered')));
            return false;
        }

        // grab our API instance
        if (!$this->twitter) {
            $this->twitter = new Api;
        }

        if ($this->twitter->login($email, $password)) {
            $this->email = $email;
            $this->password = $password;
            $this->set_page_complete($this->pages[2], true);
            return true;
        } else {
            $t = Application::getInstance()->translate;
            $this->login['email']->set_text('');
            $this->login['password']->set_text('');
            $error->set_markup(sprintf('<span color="red">%s</span>', $t->_('Authentication Error, Please retype your account and password.')));
            return false;
        }
    }

    /**
     * Stores the values in config and boots the real client up
     *
     * @return void
     */
    public function save() {
        $config = Application::getInstance()->config;
        $config['accounts'] = array();
        // TODO: crypt the password
        $config['accounts']['email'][] = $this->email;
        $config['accounts']['password'][] = $this->password;
        // TODO: store rest of settings
        $window = new Main();
        $window->show_all();
        $this->disconnect($this->id);
        $this->destroy();
    }
}