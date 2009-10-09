<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Dump of configuration variables
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/api.php';

/**
 * Gives a full dump of configuration variables for this instance
 * of StatusNet, minus variables that may be security-sensitive (like
 * passwords).
 * URL: http://identi.ca/api/statusnet/config.(xml|json)
 * Formats: xml, json
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiStatusnetConfigAction extends TwitterApiAction
{
    var $keys = array(
        'site' => array('name', 'server', 'theme', 'path', 'fancy', 'language',
                        'email', 'broughtby', 'broughtbyurl', 'closed',
                        'inviteonly', 'private'),
        'license' => array('url', 'title', 'image'),
        'nickname' => array('featured'),
        'throttle' => array('enabled', 'count', 'timespan'),
        'xmpp' => array('enabled', 'server', 'user')
    );

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);
        return true;
    }

    /**
     * Handle the request
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        switch ($this->format) {
        case 'xml':
            $this->init_document('xml');
            $this->elementStart('config');

            // XXX: check that all sections and settings are legal XML elements

            common_debug(var_export($this->keys, true));

            foreach ($this->keys as $section => $settings) {
                $this->elementStart($section);
                foreach ($settings as $setting) {
                    $value = common_config($section, $setting);
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    } else if ($value === false) {
                        $value = 'false';
                    } else if ($value === true) {
                        $value = 'true';
                    }
                    $this->element($setting, null, $value);
                }
                $this->elementEnd($section);
            }
            $this->elementEnd('config');
            $this->end_document('xml');
            break;
        case 'json':
            $result = array();
            foreach ($this->keys as $section => $settings) {
                $result[$section] = array();
                foreach ($settings as $setting) {
                    $result[$section][$setting]
                        = common_config($section, $setting);
                }
            }
            $this->init_document('json');
            $this->show_json_objects($result);
            $this->end_document('json');
            break;
        default:
            $this->clientError(
                _('API method not found!'),
                404,
                $this->format
            );
            break;
        }
    }

}

