<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Authorize an OAuth request token
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apioauthstore.php';

/**
 * Authorize an OAuth request token
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthAuthorizeAction extends Action
{
    var $oauth_token;
    var $callback;
    var $app;
    var $nickname;
    var $password;
    var $store;

    /**
     * Is this a read-only action?
     *
     * @return boolean false
     */

    function isReadOnly($args)
    {
        return false;
    }

    function prepare($args)
    {
        parent::prepare($args);

        common_debug(var_export($_REQUEST, true));

        $this->nickname    = $this->trimmed('nickname');
        $this->password    = $this->arg('password');
        $this->oauth_token = $this->arg('oauth_token');
        $this->callback    = $this->arg('oauth_callback');
        $this->store       = new ApiStatusNetOAuthDataStore();

        return true;
    }

    function getApp()
    {
        // Look up the full req token

        $req_token = $this->store->lookup_token(null,
                                                'request',
                                                $this->oauth_token);

        if (empty($req_token)) {

            common_debug("Couldn't find request token!");

            $this->clientError(_('Bad request.'));
            return;
        }

        // Look up the app

        $app = new Oauth_application();
        $app->consumer_key = $req_token->consumer_key;
        $result = $app->find(true);

        if (!empty($result)) {
            $this->app = $app;
            return true;

        } else {
            common_debug("couldn't find the app!");
            return false;
        }
    }

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            /* Use a session token for CSRF protection. */
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->showForm(_('There was a problem with your session token. '.
                                  'Try again, please.'));
                return;
            }

            $this->handlePost();

        } else {

            common_debug('ApiOauthAuthorize::handle()');

            if (empty($this->oauth_token)) {

                common_debug("No request token found.");

                $this->clientError(_('Bad request.'));
                return;
            }

            if (!$this->getApp()) {
                $this->clientError(_('Bad request.'));
                return;
            }

            common_debug("Requesting auth for app: $app->name.");

            $this->showForm();
        }
    }

    function handlePost()
    {
        /* Use a session token for CSRF protection. */

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if (!$this->getApp()) {
            $this->clientError(_('Bad request.'));
            return;
        }

        // is the user already logged in?

        // check creds

        if (!common_logged_in()) {
            $user = common_check_user($this->nickname, $this->password);
            if (empty($user)) {
                $this->showForm(_("Invalid nickname / password!"));
                return;
            }
        }

        if ($this->arg('allow')) {

            $this->store->authorize_token($this->oauth_token);

            // if we have a callback redirect and provide the token

            if (!empty($this->callback)) {
                $target_url = $this->callback . '?oauth_token=' . $this->oauth_token;
                common_redirect($target_url, 303);
            }

            // otherwise inform the user that the rt was authorized

            $this->elementStart('p');

            // XXX: Do verifier code?

            $this->raw(sprintf(_("The request token %s has been authorized. " .
                                 'Please exchange it for an access token.'),
                               $this->oauth_token));

            $this->elementEnd('p');

        } else if ($this->arg('deny')) {

            $this->elementStart('p');

            $this->raw(sprintf(_("The request token %s has been denied."),
                               $this->oauth_token));

            $this->elementEnd('p');
        } else {
            $this->clientError(_('Unexpected form submission.'));
            return;
        }
    }

    function showForm($error=null)
    {
        $this->error = $error;
        $this->showPage();
    }

    function showScripts()
    {
        parent::showScripts();
      //  $this->autofocus('nickname');
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */

    function title()
    {
        return _('An application would like to connect to your account');
    }

    /**
     * Show page notice
     *
     * Display a notice for how to use the page, or the
     * error if it exists.
     *
     * @return void
     */

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        } else {
            $instr  = $this->getInstructions();
            $output = common_markup_to_html($instr);

            $this->raw($output);
        }
    }

    /**
     * Shows the authorization form.
     *
     * @return void
     */

    function showContent()
    {
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_login',
                                           'class' => 'form_settings',
                                           'action' => common_local_url('apioauthauthorize')));

        $this->hidden('token', common_session_token());
        $this->hidden('oauth_token', $this->oauth_token);
        $this->hidden('oauth_callback', $this->callback);

        $this->elementStart('fieldset');

        $this->elementStart('ul');
        $this->elementStart('li');
        if (!empty($this->app->icon)) {
            $this->element('img', array('src' => $this->app->icon));
        }
        $this->elementEnd('li');
        $this->elementStart('li');

        $access = ($this->app->access_type & Oauth_application::$writeAccess) ?
          'access and update' : 'access';

        $msg = _("The application <b>%s</b> by <b>%s</b> would like " .
                 "the ability to <b>%s</b> your account data.");

        $this->raw(sprintf($msg,
                           $this->app->name,
                           $this->app->organization,
                           $access));

        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementEnd('fieldset');

        if (!common_logged_in()) {

            $this->elementStart('fieldset');
            $this->element('legend', null, _('Login'));
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            $this->input('nickname', _('Nickname'));
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->password('password', _('Password'));
            $this->elementEnd('li');
            $this->elementEnd('ul');

            $this->elementEnd('fieldset');

        }

        $this->element('input', array('id' => 'deny_submit',
                                      'class' => 'submit',
                                      'name' => 'deny',
                                      'type' => 'submit',
                                      'value' => _('Deny')));

        $this->element('input', array('id' => 'allow_submit',
                                      'class' => 'submit',
                                      'name' => 'allow',
                                      'type' => 'submit',
                                      'value' => _('Allow')));

        $this->elementEnd('form');
    }

    /**
     * Instructions for using the form
     *
     * For "remembered" logins, we make the user re-login when they
     * try to change settings. Different instructions for this case.
     *
     * @return void
     */

    function getInstructions()
    {
        return _('Allow or deny access to your account information.');

    }

    /**
     * A local menu
     *
     * Shows different login/register actions.
     *
     * @return void
     */

    function showLocalNav()
    {
    }

}
