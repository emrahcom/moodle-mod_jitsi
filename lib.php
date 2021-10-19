<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module jitsi
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the jitsi specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_jitsi
 * @copyright  2019 Sergio Comerón Sánchez-Paniagua <sergiocomeron@icloud.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function jitsi_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the jitsi into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $jitsi Submitted data from the form in mod_form.php
 * @param mod_jitsi_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted jitsi record
 */
function jitsi_add_instance($jitsi,  $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/jitsi/locallib.php');
    $time = time();
    $jitsi->timecreated = $time;
    $cmid       = $jitsi->coursemodule;
    $jitsi->token = bin2hex(random_bytes(32));
    $jitsi->id = $DB->insert_record('jitsi', $jitsi);
    jitsi_update_calendar($jitsi, $cmid);

    return $jitsi->id;
}

/**
 * Updates an instance of the jitsi in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $jitsi An object from the form in mod_form.php
 * @param mod_jitsi_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function jitsi_update_instance($jitsi,  $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/jitsi/locallib.php');

    $jitsi->timemodified = time();
    $jitsi->id = $jitsi->instance;
    $cmid       = $jitsi->coursemodule;

    $result = $DB->update_record('jitsi', $jitsi);
    jitsi_update_calendar($jitsi, $cmid);

    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every assignment event in the site is checked, else
 * only assignment events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @param int|stdClass $instance Jitsi module instance or ID.
 * @param int|stdClass $cm Course module object or ID.
 * @return bool
 */
function jitsi_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/jitsi/locallib.php');

    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('jitsi', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                $cm = (object)array('id' => $cm);
            }
        } else {
            $cm = get_coursemodule_from_instance('jitsi', $instance->id);
        }
        jitsi_update_calendar($instance, $cm->id);
        return true;
    }

    if ($courseid) {
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$jitsis = $DB->get_records('jitsi', array('course' => $courseid))) {
            return true;
        }
    } else {
        return true;
    }

    foreach ($jitsis as $jitsi) {
        $cm = get_coursemodule_from_instance('jitsi', $jitsi->id);
        jitsi_update_calendar($jitsi, $cm->id);
    }

    return true;
}

/**
 * Removes an instance of the jitsi from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function jitsi_delete_instance($id) {
    global $CFG, $DB;

    if (! $jitsi = $DB->get_record('jitsi', array('id' => $id))) {
        return false;
    }

    $result = true;
    $records = $DB->get_records('jitsi_record', array('jitsi' => $jitsi->id));
    foreach ($records as $record) {
        // deleterecordyoutube($record->id);
        if ($record->deleted !=1) {
            marktodelete($record->id, 2);
        } else {
            marktodelete($record->id, 1);
        }
    }

    if (! $DB->delete_records('jitsi', array('id' => $jitsi->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Jitsi private sessions on profile user
 *
 * @param tree $tree tree
 * @param stdClass $user user
 * @param int $iscurrentuser iscurrentuser
 */
function jitsi_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser) {
    global $DB, $CFG, $USER;
    if ($CFG->jitsi_privatesessions == 1) {
        $urlparams = array('user' => $user->id);
        $url = new moodle_url('/mod/jitsi/viewpriv.php', $urlparams);
        $category = new core_user\output\myprofile\category('jitsi',
            get_string('jitsi', 'jitsi'), null);
        $tree->add_category($category);
        if ($iscurrentuser == 0) {
            $node = new core_user\output\myprofile\node('jitsi', 'jitsi',
                get_string('privatesession', 'jitsi', $user->firstname), null, $url);
        } else {
            $node = new core_user\output\myprofile\node('jitsi', 'jitsi',
                get_string('myprivatesession', 'jitsi'), null, $url);
        }
        $tree->add_node($node);
    }
    return true;
}

/**
 * Base 64 encode
 * @param string $inputstr - Input to encode
 */
function base64urlencode($inputstr) {
    return strtr(base64_encode($inputstr), '+/=', '-_,');
}

/**
 * Base 64 decode
 * @param string $inputstr - Input to decode
 */
function base64urldecode($inputstr) {
    return base64_decode(strtr($inputstr, '-_,', '+/='));
}

/**
 * Sanitize strings
 * @param string $string - The string to sanitize.
 * @param boolean $forcelowercase - Force the string to lowercase?
 * @param boolean $anal - If set to *true*, will remove all non-alphanumeric characters.
 */
function string_sanitize($string, $forcelowercase = true, $anal = false) {
    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")",
            "_", "=", "+", "[", "{", "]", "}", "\\", "|", ";", ":", "\"",
            "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "-", $clean);
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean;
    return ($forcelowercase) ?
        (function_exists('mb_strtolower')) ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean) :
        $clean;
}

/**
 * Create session
 * @param int $teacher - Moderation
 * @param int $cmid - Course module
 * @param string $avatar - Avatar
 * @param string $nombre - Name
 * @param string $session - sesssion name
 * @param string $mail - mail
 * @param stdClass $jitsi - Jitsi session
 * @param bool $universal - 
 * @param stdClass $user - 
 * @param int $timetsamp - 
 * @param int $codet - 
 */
function createsession($teacher, $cmid, $avatar, $nombre, $session, $mail, $jitsi, $universal = false,
        $user = null) {
    global $CFG, $DB, $PAGE, $USER;
    $sessionnorm = str_replace(array(' ', ':', '"'), '', $session);
    if ($teacher == 1) {
        $teacher = true;
        $affiliation = "owner";
    } else {
        $teacher = false;
        $affiliation = "member";
    }
    if ($user != null) {
        $context = context_system::instance();
    } else {
        $context = context_module::instance($cmid);
    }

    if ($universal == false) {
        if (!has_capability('mod/jitsi:view', $context)) {
            notice(get_string('noviewpermission', 'jitsi'));
        }
    }
    $header = json_encode([
        "kid" => "jitsi/custom_key_name",
        "typ" => "JWT",
        "alg" => "HS256"
    ], JSON_UNESCAPED_SLASHES);
    $base64urlheader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payload  = json_encode([
        "context" => [
            "user" => [
                "affiliation" => $affiliation,
                "avatar" => $avatar,
                "name" => $nombre,
                "email" => $mail,
                "id" => ""
            ],
            "group" => ""
        ],
        "aud" => "jitsi",
        "iss" => $CFG->jitsi_app_id,
        "sub" => $CFG->jitsi_domain,
        "room" => urlencode($sessionnorm),
        "exp" => time() + 24 * 3600,
        "moderator" => $teacher
    ], JSON_UNESCAPED_SLASHES);
    $base64urlpayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $secret = $CFG->jitsi_secret;
    $signature = hash_hmac('sha256', $base64urlheader . "." . $base64urlpayload, $secret, true);
    $base64urlsignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64urlheader . "." . $base64urlpayload . "." . $base64urlsignature;
    echo "<script src=\"//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js\"></script>";
    echo "<script src=\"https://".$CFG->jitsi_domain."/external_api.js\"></script>\n";

    $streamingoption = '';
    if (($CFG->jitsi_livebutton == 1) && (has_capability('mod/jitsi:record', $PAGE->context))
        && ($CFG->jitsi_streamingoption == 0)) {
        $streamingoption = 'livestreaming';
    }

    $youtubeoption = '';
    if ($CFG->jitsi_shareyoutube == 1) {
        $youtubeoption = 'sharedvideo';
    }
    $bluroption = '';
    if ($CFG->jitsi_blurbutton == 1) {
        $bluroption = 'videobackgroundblur';
    }
    $security = '';
    if ($CFG->jitsi_securitybutton == 1) {
        $security = 'security';
    }
    $record = '';
    if ($CFG->jitsi_record == 1) {
        $record = 'recording';
    }
    $invite = '';
    $muteeveryone = '';
    $mutevideoeveryone = '';
    if ($teacher) {
        $muteeveryone = 'mute-everyone';
        $mutevideoeveryone = 'mute-video-everyone';
    }

    $buttons = "['microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
        'fodeviceselection', 'hangup', 'chat', '".$record."', 'etherpad', '".$youtubeoption."',
        'settings', 'raisehand', 'videoquality', '".$streamingoption."','filmstrip', '".$invite."', 'stats',
        'shortcuts', 'tileview', '".$bluroption."', 'download', 'help', '".$muteeveryone."',
        '".$mutevideoeveryone."', '".$security."']";

    echo "<div class=\"row\">";
    echo "<div class=\"col-sm\">";
    //-------------->>>>>!!!!!!Hay que elegir que cuenta usar, de momento le meto a pelo el primero!!!!!!!<<<<<<--------------
    $acount = $DB->get_record('jitsi_record_acount', array('name'=>'Google'));
    if ($user == null) {
        if ($CFG->jitsi_livebutton == 1 && has_capability('mod/jitsi:record', $PAGE->context)
            // && get_config('mod_jitsi', 'jitsi_clientrefreshtoken') != null
            && $acount != null
            // && get_config('mod_jitsi', 'jitsi_clientaccesstoken') != null
            && ($CFG->jitsi_streamingoption == 1)) {
                echo "<div class=\"text-right\">";
                echo "<div class=\"custom-control custom-switch\">";
                echo "<input type=\"checkbox\" class=\"custom-control-input\" id=\"recordSwitch\" onClick=\"mostrarLog($(this));\">";
                echo "  <label class=\"custom-control-label\" for=\"recordSwitch\">Record</label>";
                echo "</div>";
                echo "</div>";
        }
    }

    echo "</div></div>";
    echo "<hr>";

    echo "<script>\n";
    echo "const domain = \"".$CFG->jitsi_domain."\";\n";
    echo "const options = {\n";
    echo "configOverwrite: {\n";
    if ($CFG->jitsi_deeplink == 0) {
        echo "disableDeepLinking: true,\n";
    }

    if ($CFG->jitsi_reactions == 0) {
        echo "disableReactions: true,\n";
    }

    if ($CFG->jitsi_livebutton == 0) {
        echo "liveStreamingEnabled: false,\n";
    }

    echo "toolbarButtons: ".$buttons.",\n";
    echo "disableProfile: true,\n";
    echo "prejoinPageEnabled: false,";
    echo "channelLastN: ".$CFG->jitsi_channellastcam.",\n";
    echo "startWithAudioMuted: true,\n";
    echo "startWithVideoMuted: true,\n";
    echo "},\n";
    echo "roomName: \"".urlencode($sessionnorm)."\",\n";
    if ($CFG->jitsi_app_id != null && $CFG->jitsi_secret != null) {
        echo "jwt: \"".$jwt."\",\n";
    }
    if ($CFG->branch < 36) {
        if ($CFG->theme == 'boost' || in_array('boost', $themeconfig->parents)) {
            echo "parentNode: document.querySelector('#region-main .card-body'),\n";
        } else {
            echo "parentNode: document.querySelector('#region-main'),\n";
        }
    } else {
        echo "parentNode: document.querySelector('#region-main'),\n";
    }
    echo "interfaceConfigOverwrite:{\n";
    echo "TOOLBAR_BUTTONS: ".$buttons.",\n";
    echo "SHOW_JITSI_WATERMARK: true,\n";
    echo "JITSI_WATERMARK_LINK: '".$CFG->jitsi_watermarklink."',\n";
    echo "},\n";
    echo "width: '100%',\n";
    echo "height: 650,\n";
    echo "}\n";
    echo "const api = new JitsiMeetExternalAPI(domain, options);\n";
    echo "api.executeCommand('displayName', '".$nombre."');\n";
    echo "api.executeCommand('avatarUrl', '".$avatar."');\n";

    if ($CFG->jitsi_finishandreturn == 1) {
        echo "api.on('readyToClose', () => {\n";
        echo "    api.dispose();\n";
        if ($universal == false && $user == null) {
            echo "    location.href=\"".$CFG->wwwroot."/mod/jitsi/view.php?id=".$cmid."\";";
        } else if ($universal == true && $user == null) {
            // echo "    location.href=\"".$CFG->wwwroot."/mod/jitsi/formuniversal.php?id=".$cmid."&c=".$code."\";";
            echo "    location.href=\"".$CFG->wwwroot."/mod/jitsi/formuniversal.php?t=".$jitsi->token."\";";

        // } else if ($user != null && !$code) {
        } else if ($user != null) {
            echo "    location.href=\"".$CFG->wwwroot."/mod/jitsi/viewpriv.php?user=".$user."\";";
        }
        echo  "});\n";
    }
    echo "function mostrarLog(e){";
    echo "if (e.is(':checked')) {";
    echo "console.log(\"Switch cambiado a activado\");";
    echo "stream();";
    echo "} else {";
    echo "console.log(\"Switch cambiado a desactivado\");";
    echo "stopStream();";
    echo "}";
    echo "}";

    if ($CFG->jitsi_password != null) {
        echo "api.addEventListener('participantRoleChanged', function(event) {";
        echo "    if (event.role === \"moderator\") {";
        echo "        api.executeCommand('password', '".$CFG->jitsi_password."');";
        echo "    }";
        echo "});";
        echo "api.on('passwordRequired', function ()";
        echo "{";
        echo "    api.executeCommand('password', '".$CFG->jitsi_password."');";
        echo "});";
    }

    if ($user == null) {
        echo "api.addEventListener('recordingStatusChanged', function(event) {\n";
        echo "    if (event['on']){\n";
        echo "document.getElementById(\"recordSwitch\").checked = true;\n";
        echo "    } else if (!event['on']){\n";
        echo "document.getElementById(\"recordSwitch\").checked = false;\n";
        echo "    }\n";
        echo "    require(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {\n";
        echo "        ajax.call([{\n";
        echo "            methodname: 'mod_jitsi_state_record',\n";
        echo "            args: {jitsi:".$jitsi->id.", state: event['on']},\n";
        echo "            done: console.log(\"Cambio grabación\"),\n";
        echo "            fail: notification.exception\n";
        echo "        }]);\n";
        echo "        console.log(event['on']);\n";
        echo "    })\n";
        echo "});\n";

        echo "function stream(){\n";

        echo "    require(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {\n";
        echo "       var respuesta = ajax.call([{\n";
        echo "            methodname: 'mod_jitsi_create_stream',\n";
        echo "            args: {session:'".$session."', jitsi:'".$jitsi->id."'},\n";

        echo "       }]);\n";
        echo "       respuesta[0].done(function(response) {\n";
        echo "          api.executeCommand('startRecording', {\n";
        echo "              mode: 'stream',\n";
        echo "              youtubeStreamKey: response \n";
        echo "          })\n";
        echo "            console.log(response);";
        echo ";})";
        echo  ".fail(function(ex) {console.log(ex);});";
        echo "    })\n";
        echo "}\n";

        echo "function stopStream(){\n";
        echo "api.executeCommand('stopRecording', 'stream');\n";
        echo "}\n";

        echo "function sendlink(){\n";
        echo "        var nombreform = document.getElementById(\"nombrelink\").value;";
        echo "        var mailform = document.getElementById(\"maillink\").value;";
        echo "    require(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {\n";
        echo "       var respuesta = ajax.call([{\n";
        echo "            methodname: 'mod_jitsi_create_link',\n";
        echo "            args: {jitsi: ".$jitsi->id."},\n";
        echo "       }]);\n";
        echo "       respuesta[0].done(function(response) {\n";
        echo "            alert(\"Enviado\");";
        echo ";})";
        echo  ".fail(function(ex) {console.log(ex);});";
        echo "    })\n";
        echo "}\n";
    }
    echo "</script>\n";
}

/**
 * Check if a date is out of time
 * @param int $timestamp date
 * @param stdClass $jitsi jitsi instance
 */
function istimedout($jitsi) {
    if (time() > $jitsi->validitytime) {
        return true;
    } else {
        return false;
    }
}

/**
 * Generate the time error
 * @param stdClass $jitsi jitsi instance
 */
function generateErrotTime($jitsi) {
    global $CFG;
    if ($jitsi->validitytime == 0 || $CFG->jitsi_invitebuttons == 0){
        return get_string('invitationsnotactivated', 'jitsi');
    } else {
        return get_string('linkexpiredon', 'jitsi', userdate($jitsi->validitytime));
    }
}

/**
 * Check if a code is original
 * @param int $code code to check
 * @param stdClass $jitsi jitsi instance
 */
function isoriginal($code, $jitsi) {
    // if ($code == $jitsi->timecreated + $jitsi->id) {
    if ($code == ($jitsi->timecreated + $jitsi->id)) {
        $original = true;
    } else {
        $original = false;
    }
    return $original;
}

/**
 * Generate code from a jitsi
 * @param stdClass $jitsi jitsi instance
 */
function generatecode($jitsi) {
    return $jitsi->timecreated + $jitsi->id;
}

/**
 * Send notification when user enter on private session
 * @param stdClass $fromuser - User entering the private session
 * @param stdClass $touser - User session owner
 */
function sendnotificationprivatesession($fromuser, $touser) {
    global $CFG;
    $message = new \core\message\message();
    $message->component = 'mod_jitsi';
    $message->name = 'onprivatesession';
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $touser;
    $message->subject = get_string('userenter', 'jitsi', $fromuser->firstname);
    $message->fullmessage = get_string('userenter', 'jitsi', $fromuser->firstname .' '. $fromuser->lastname);
    $message->fullmessageformat = FORMAT_MARKDOWN;
    $message->fullmessagehtml = get_string('user').' <a href='.$CFG->wwwroot.'/user/profile.php?id='.$fromuser->id.'> '
    . $fromuser->firstname .' '. $fromuser->lastname
    . '</a> '.get_string('hasentered', 'jitsi').'. '.get_string('click', 'jitsi').'<a href='
    . new moodle_url('/mod/jitsi/viewpriv.php', array('user' => $touser->id))
    .'> '.get_string('here', 'jitsi').'</a> '.get_string('toenter', 'jitsi');
    $message->smallmessage = get_string('userenter', 'jitsi', $fromuser->firstname .' '. $fromuser->lastname);
    $message->notification = 1;
    $message->contexturl = new moodle_url('/mod/jitsi/viewpriv.php', array('user' => $touser->id));
    $message->contexturlname = 'Private session';
    $content = array('*' => array('header' => '', 'footer' => ''));
    $message->set_additional_content('email', $content);
    $messageid = message_send($message);
}

/**
 * Send notification when user enter on private session
 * @param stdClass $fromuser - User entering the private session
 * @param stdClass $touser - User session owner
 */
function sendcallprivatesession($fromuser, $touser) {
    global $CFG;
    $message = new \core\message\message();
    $message->component = 'mod_jitsi';
    $message->name = 'callprivatesession';
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $touser;
    $message->subject = get_string('usercall', 'jitsi', $fromuser->firstname);
    $message->fullmessage = get_string('usercall', 'jitsi', $fromuser->firstname .' '. $fromuser->lastname);
    $message->fullmessageformat = FORMAT_MARKDOWN;
    $message->fullmessagehtml = get_string('user').' <a href='.$CFG->wwwroot.'/user/profile.php?id='.$fromuser->id.'> '
    . $fromuser->firstname .' '. $fromuser->lastname
    . '</a> '.get_string('iscalling', 'jitsi').'. '.get_string('click', 'jitsi').'<a href='
    . new moodle_url('/mod/jitsi/viewpriv.php', array('user' => $fromuser->id))
    .'> '.get_string('here', 'jitsi').'</a> '.get_string('toenter', 'jitsi');
    $message->smallmessage = get_string('usercall', 'jitsi', $fromuser->firstname .' '. $fromuser->lastname);
    $message->notification = 1;
    $message->contexturl = new moodle_url('/mod/jitsi/viewpriv.php', array('user' => $fromuser->id));
    $message->contexturlname = 'Private session';
    $content = array('*' => array('header' => '', 'footer' => ''));
    $message->set_additional_content('email', $content);
    $messageid = message_send($message);
}

/**
 * Mark Jitsi record to delete
 * @param int $idrecord - Jitsi record to delete
 */
function marktodelete($idrecord, $option) {
    global $DB;
    $record = $DB->get_record('jitsi_record', array('id' => $idrecord));
    if ($option == 1) {
        $record->deleted = 1;
    } else if ($option == 2) {
        $record->deleted = 2;
    }
    $DB->update_record('jitsi_record', $record);
    // $DB->delete_records('jitsi_record', array('id' => $idrecord));
}

// function delete_jitsi_record($idrecord) {

// }

/**
 * Delete Jitsi record
 * @param int $idrecord - Jitsi record to delete
 */
// function delete_jitsi_record($idrecord) {
function delete_jitsi_record($link) {
        global $DB;
    
    // $DB->delete_records('jitsi_record', array('id' => $idrecord));
    $DB->delete_records('jitsi_record', array('link' => $link));

    
}

function isDeletable($linkrecord) {
    $res = true;
    global $DB;
    $records = $DB->get_records('jitsi_record', array('link'=>$linkrecord));
    foreach ($records as $record) {
        if ($record->deleted == 0) {
            $res = false;
        }
    }
    return $res;
}

/**
 * Delete Record from youtube
 * @param int $idrecord - Jitsi record to delete
 */
function deleterecordyoutube($idrecord) {
    global $CFG, $DB, $PAGE;
    // Api google.
    $jitsirecord = $DB->get_record('jitsi_record', array('id' => $idrecord));
    if (isDeletable($jitsirecord->link)) {
        if (!file_exists(__DIR__ . '/api/vendor/autoload.php')) {
            throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
        }
        require_once(__DIR__ . '/api/vendor/autoload.php');

        $client = new Google_Client();

        $client->setClientId($CFG->jitsi_oauth_id);
        $client->setClientSecret($CFG->jitsi_oauth_secret);

        $tokensessionkey = 'token-' . "https://www.googleapis.com/auth/youtube";

        //-------------->>>>>!!!!!!Hay que elegir que cuenta usar, de momento le meto a pelo el primero!!!!!!!<<<<<<--------------
        $acount = $DB->get_record('jitsi_record_acount', array('name'=>'Google'));

        $_SESSION[$tokensessionkey] = $acount->clientaccesstoken;
        $client->setAccessToken($_SESSION[$tokensessionkey]);
        $t = time();
        $timediff = $t - $acount->tokencreated;

        if ($timediff > 3599) {
            $newaccesstoken = $client->fetchAccessTokenWithRefreshToken($acount->clientrefreshtoken);
            $acount->clientaccesstoken = $newaccesstoken["access_token"];
            $newrefreshaccesstoken = $client->getRefreshToken();
            $acount->clientrefreshtoken = $newrefreshaccesstoken;
            $acount->tokencreated = time();
        }

        $youtube = new Google_Service_YouTube($client);

        if ($client->getAccessToken($idrecord)) {
            try {
                // $jitsirecord = $DB->get_record('jitsi_record', array('id' => $idrecord));
                $youtube->videos->delete($jitsirecord->link);
                // delete_jitsi_record($idrecord);
                delete_jitsi_record($jitsirecord->link);

            } catch (Google_Service_Exception $e) {
                throw new \Exception("exception".$e->getMessage());
            } catch (Google_Exception $e) {
                throw new \Exception("exception".$e->getMessage());
            }
        }
    }
}

 /**
  * Get icon mapping for font-awesome.
  */
function mod_jitsi_get_fontawesome_icon_map() {
    return [
        'mod_forum:t/add' => 'share-alt-square',
    ];
}
