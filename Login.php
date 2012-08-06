<?php
/**
 * Login.php - \Callicore\Twitter\Login dialog for logging in 
 * This is released under the MIT, see license.txt for details
 *
 * @author       Elizabeth Smith <auroraeosrose@php.net>
 * @copyright    Elizabeth Smith (c)2009
 * @link         http://callicore.net
 * @license      http://www.opensource.org/licenses/mit-license.php MIT
 * @version      $Id: Login.php 21 2009-04-26 01:18:00Z auroraeosrose $
 * @since        Php 5.3.0
 * @package      callicore
 * @subpackage   lib
 * @filesource
 */

/**
 * Namespace for application
 */
namespace Callicore\Twitter;
use Gtk; // for constants
use GtkDialog; // extend the regular dialog class
use GtkTable; // for layout of dialog
use GtkLabel; // for form
use GtkEntry; // for form

class Login extends GtkDialog {
    protected $emailentry;
    protected $passwordentry;

    public function __construct($parent) {
        parent::__construct('Login to Twitter', $parent, Gtk::DIALOG_MODAL,
            array(
                    Gtk::STOCK_OK, Gtk::RESPONSE_OK,
                    Gtk::STOCK_CANCEL, Gtk::RESPONSE_CANCEL));
        $table = new GtkTable();
        $email = new GtkLabel('Email:');
        $table->attach($email, 0, 1, 0, 1);
        $password = new GtkLabel('Password:');
        $table->attach($password, 0, 1, 1, 2);
        $this->emailentry = new GtkEntry();
        $table->attach($this->emailentry, 1, 2, 0, 1);
        $this->passwordentry = new GtkEntry();
        $table->attach($this->passwordentry, 1, 2, 1, 2);
        $this->passwordentry->set_visibility(false);
        $this->vbox->add($table);
        $this->errorlabel = new GtkLabel();
        $this->vbox->add($this->errorlabel);
        $this->show_all();
    }

    public function check_login($twitter) {
        $this->errorlabel->set_text('');
        $email = $this->emailentry->get_text();
        $password = $this->passwordentry->get_text();
        if (empty($password) || empty($password)) {
            $this->errorlabel->set_markup('<span color="red">Name and Password must be entered</span>');
            return false;
        }
        if ($twitter->login($email, $password)) {
            return true;
        } else {
            $this->errorlabel->set_markup('<span color="red">Authentication Error</span>');
            return false;
        }
    }
}