<?php

/*
@license New BSD License - See LICENSE file for details.
@copyright (C) 2022 SURF BV
*/

namespace TestServer;

class TestServerView
{
    public function ShowRoot($args = array()) : void {
        $this->begin();
        echo <<<HTML
<h1>Tiqr Test Server</h1>

<p>Hi!</p>
<p>This is a Tiqr TestServer. The TestServer is used for testing tiqr clients (i.e. a smartphone app that supports the Tiqr protocol).</p>
<a>Here you can <a href="/start-enrollment">enroll a new user</a> and <a href="/start-authenticate">authenticate any existing user</a> or <a href="/list-users">select a specific user to authenticate</a>.</p>
<p>Note: For your tiqr client to be able to work with this TestServer its <a href="/show-config">configuration</a> must match this server's configuration. If anything goes wrong, have look at the TestServer's <a href="/show-logs">logs.</a></p>
<hr />
<h2>Quick links</h2>
<ul>
<li><a href="/start-enrollment">Enroll a new user</a></li>
<li><a href="/start-authenticate">Start authentication</a></li>
<li><a href="/list-users">List users</a></li>
<li><a href="/show-logs">Show logs</a></li>
<li><a href="/show-config">Show config</a></li>
</ul>
HTML;
        $this->end();
    }

    public function ListUsers($users) {
        $this->begin();
        echo <<<HTML
<h1>List of users</h1>
<p>This is the list of user IDs that are registered on this server. Click a user ID to start an authentication for that user.
This also gives you the option to start the authentication by sending a push notification.</p>
<table border="1">
<tr>
    <th>userId</th>    
    <th>displayName (version | User-Agent)</th>
    <th>notificationType</th>
    <th>notificationAddress</th>
</tr>
HTML;
        foreach ($users as $user) {
            $user['userId'] = $user['userId'] ?? '—';
            $user['displayName'] = $user['displayName'] ?? '—';
            $user['notificationType'] = $user['notificationType'] ?? '—';
            $user['notificationAddress'] = $user['notificationAddress'] ?? '—';
            if (strlen($user['notificationAddress']) > 20) {
                $user['notificationAddress'] = '<code>' . substr($user['notificationAddress'], 0, 10) . '</code>...<code>' . substr($user['notificationAddress'], -10) . '</code> (' . strlen($user['notificationAddress']) . ')';
            } else {
                $user['notificationAddress'] = '<code>'.$user['notificationAddress'].'</code>';
            }
            $user['secret'] = $user['secret'] ?? '—';
            echo <<<HTML
<tr>
    <td><a href="/start-authenticate?user_id={$user['userId']}"><code>{$user['userId']}</code></a></td>
    <td><code>{$user['displayName']}</code></td>
    <td><code>{$user['notificationType']}</code></td>
    <td>{$user['notificationAddress']}</td>
</tr>
HTML;
        }
        echo "</table>";
        $this->end();
    }

    public function StartEnrollment(string $enroll_string, string $user_id, string $session_id) : void {
        $image_url = "/qr?code=" . urlencode($enroll_string);
        $expire = htmlentities(\Tiqr_Service::ENROLLMENT_EXPIRE);
        $enroll_string = htmlentities($enroll_string);
        $get_enrollment_status = '/get-enrollment-status?session_id=' . urlencode($session_id);
        $user_id = htmlentities($user_id);
        $this->begin();
        echo <<<HTML
<h1>Enroll a new user $user_id</h1>
<p>Scan the QR code below using the Tiqr app to start the enrollment of a new user <code>$user_id</code>. When using the smart phone's browser you can tap on the QR code to open the link it contains.</p>
<p>You can use this QR code only once. You must complete the enrollment within $expire seconds.</p>
<a href="$enroll_string"><img src="$image_url" /></a> <br />
<br />
<code>$enroll_string</code>
<br />
<br />
<p><a href="/start-enrollment">Refresh enrollemnt QR code</a></p>
<p><a href="$get_enrollment_status">Get enrollment status</a></p>
HTML;
        $this->end();
    }

    public function ShowEnrollmentStatus(string $status, string $session_id)
    {
        $statusmap = array(
            \Tiqr_Service::ENROLLMENT_STATUS_IDLE => 'ENROLLMENT_STATUS_IDLE',
            \Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED => 'ENROLLMENT_STATUS_INITIALIZED',
            \Tiqr_Service::ENROLLMENT_STATUS_RETRIEVED => 'ENROLLMENT_STATUS_RETRIEVED',
            \Tiqr_Service::ENROLLMENT_STATUS_PROCESSED => 'ENROLLMENT_STATUS_PROCESSED',
            \Tiqr_Service::ENROLLMENT_STATUS_FINALIZED => 'ENROLLMENT_STATUS_FINALIZED',
        );
        $statusdescriptionmap = array(
            \Tiqr_Service::ENROLLMENT_STATUS_IDLE => 'There is no enrollment going on in this session, or there was an error getting the enrollment status',
            \Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED => 'The enrollment session was started, but the tiqr client has not retrieved the metadata yet',
            \Tiqr_Service::ENROLLMENT_STATUS_RETRIEVED => 'The tiqr client has retrieved the metadata',
            \Tiqr_Service::ENROLLMENT_STATUS_PROCESSED => 'The tiqr client has sent back the tiqr authentication secret',
            \Tiqr_Service::ENROLLMENT_STATUS_FINALIZED => 'The server has stored the authentication secret',
        );

        $get_enrollment_status = '/get-enrollment-status?session_id=' . urlencode($session_id);

        $this->begin();

        echo <<<HTML
<h1>Enrollment status</h1>
    
<p>Status: <code>$status</code></p>
HTML;
        if (!isset($statusmap[$status])) {
            echo "<p>ERROR: Unknown status code</p>";
        }

        echo "<ul>";
        foreach ($statusmap as $statuscode => $statusconst) {
            $statusdescription = $statusdescriptionmap[$statuscode];
            if ($status == $statuscode) {
                echo "<li><b>$statusconst ($statuscode): $statusdescription</b></li>";
            } else {
                echo "<li>$statusconst ($statuscode): $statusdescription</li>";
            }
        }
        echo "</ul>";

        echo <<<HTML
<p></p><a href="$get_enrollment_status">Refresh enrollment status</a></p>
HTML;
        $this->end();
    }


    public function ShowQRCode($code) {
        $this->begin();
        $codeHTML = htmlentities($code);
        echo <<<HTML
HTML;

    }


    private function begin() {
        echo <<<HTML
<!doctype html>
<html lang=en>
<head>
<meta charset=utf-8>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TiqrTestServer</title>
</head>
<body>
HTML;
    }

    private function end() {
        echo <<<HTML
<br />
<a href="/">Home</a><br />
<br />
<hr />
<small>
This is a Tiqr TestServer. The TestServer is a simple web application aimed at Developers and Tester for testing a Tiqr client with the Tiqr <a href=""https://github.com/Tiqr/tiqr-server-libphp">tiqr-server-libphp library</a>. <br />
See <a href="https://tiqr.org">tiqr.org</a> for more information about the Tiqr project.
</small>
</body>
</html>
HTML;
    }

    public function StartAuthenticate(string $authenticationURL, string $user_id, string $response, string $session_key, string $secret, string $auth_session_id)
    {
        // This view can handle both authenticating a known user and an unknown user
        // If the user_id is empty, we're authenticating an unknown user
        $refreshURL = '/start-authenticate';
        $authenticationStatusURL = '/get-authentication-status?session_id=' . urlencode($auth_session_id);
        if (strlen($user_id) > 0) {
            $refreshURL.= "?user_id=" . urlencode($user_id);
            $authenticationStatusURL.= '&user_id=' . urlencode($user_id);
        }
        $sendPushNotificationURL = '/send-push-notification?user_id=' . urlencode($user_id) . '&session_key=' . urlencode($session_key) . '&session_id=' . urlencode($auth_session_id);
        $image_url = "/qr?code=" . urlencode($authenticationURL);
        $authenticationURLHTML=htmlentities($authenticationURL);
        $authentication_timeout=htmlentities(\Tiqr_Service::CHALLENGE_EXPIRE);

        $this->begin();
        echo <<<HTML
        <h1>Authenticate user $user_id</h1>
<p>Scan the QR code below using the Tiqr app. When using the smartphone's browser, you can tap on the QR code to open the link it contains instead of scanning it.</p>
<p>This QR code is valid for a limited time ($authentication_timeout seconds).</p>
<a href="$authenticationURLHTML"><img src="$image_url" /></a> <br />
<br />
<code>$authenticationURLHTML</code>
<br />
HTML;
        // We're authenticating a known user, so we can show the response and secret and offer to send a push notification
        // to the user to start the authentication process
        if (strlen($user_id)>0) {
            $userIdHTML = htmlentities($user_id);
            echo <<<HTML
<p>The correct OCRA response for this authentication (for offline validation) is: <code>$response</code></p>
<p>The OCRA secret for this user is: <code>$secret</code></p>
<p><a href="$sendPushNotificationURL">Send push notification to user $userIdHTML</a></p>
HTML;
        }
        echo <<<HTML
<p></p><a href="$refreshURL">Refresh authentication session and QR code</a></p>
<p><a href="$authenticationStatusURL">Check the authentication status of this session</a></p>
<br />
HTML;
        $this->end();
    }


    function PushResult(string $notificationresult, string $session_key, string $user_id, string $session_id) {
        $this->begin();
        $checkAuthenticationStatusURL = '/get-authentication-status?session_key=' . urlencode($session_key).'&user_id='.urlencode($user_id).'&session_id='.urlencode($session_id);
        $sendPushNotificationURL = '/send-push-notification?user_id=' . urlencode($user_id) . '&session_key=' . urlencode($session_key).'&session_id='.urlencode($session_id);
        $textHTML = htmlentities($notificationresult);
        $userIdHTML = htmlentities($user_id);
            echo <<<HTML
<p>$textHTML</p>
<p><a href="$checkAuthenticationStatusURL">Check authentication status of user <code>$userIdHTML</code></a></p>
<p><a href="$sendPushNotificationURL">Resend push notification for <code>$userIdHTML</code></a></p>
<p><a href="$checkAuthenticationStatusURL">Check authentication status</a></p>
HTML;
        $this->end();
    }

    public function Exception(string $path, \Exception $e)
    {
        http_response_code(500);
        $this->begin();
        $pathHTML=htmlentities($path);
        echo("<p>Exception while processing $pathHTML</p>");

        while ($e) {
            $message = htmlentities($e->getMessage());
            $code = htmlentities($e->getCode());
            $file = htmlentities($e->getFile());
            $line = htmlentities($e->getLine());
            $trace = htmlentities($e->getTraceAsString());
            echo <<<HTML
<p>
<table>
<tr><td>Message:</td><td><code>$message</code></td></tr>
<tr><td>Code:</td><td><code>$code</code></td></tr>
<tr><td>File:</td><td><code>$file</code></td></tr>
<tr><td>Line:</td><td><code>$line</code></td></tr>
</table>
<pre>$trace</pre><br />
</p>
HTML;
            if ($e=$e->getPrevious()) {
                echo "<p>Previous exception</p>";
            }
        }
        $this->end();
    }


    public function ShowAuthenticationStatus(string $status, string $session_id, string $user_id)
    {
        $this->begin();
        $statusHTML = htmlentities($status);
        $userIdHTML = htmlentities($user_id);
        $authenticationStatusURL = '/get-authentication-status?session_id=' . urlencode($session_id);
        if (strlen($user_id) > 0) {
            $authenticationStatusURL .= '&user_id=' . urlencode($user_id);
        }
        echo <<<HTML
<h1>Authentication status</h1>
<p>Status: <code>$statusHTML</code></p>
<p></p><a href="$authenticationStatusURL">Refresh authentication status</a></p>
HTML;
        if (strlen($user_id) > 0) {
            echo "<p><a href='/start-authenticate?user_id=" . urlencode($user_id) ."'>Start new authentication for user <code>$userIdHTML</code></a></p>";
        }
        $this->end();
    }


    /*
     * @param array $logs Array of strings with log entries to show. Entries are ordered newest first
     */
    public function ShowLogs($logs)
    {
        $this->begin();
        echo '<h1>Logs</h1>';

        foreach ($logs as $log) {
            if (strpos($log, '--== START ==--')) {
                echo '<b>' . htmlentities($log) . '</b><br /><br />';
            } else if (stripos($log, 'error')) {
                echo '<font color="red">' . htmlentities($log) . '</font><br />';
            } else if (stripos($log, 'warning')) {
                echo '<font color="darkorange">' . htmlentities($log) . '</font><br />';
            } else if (stripos($log, 'notice')) {
                echo '<font color="green">' . htmlentities($log) . '</font><br />';
            } else if (stripos($log, 'debug')) {
                echo '<font color="#a9a9a9">' . htmlentities($log) . '</font><br />';
            }
            else {
                echo htmlentities($log) . '<br />';
            }
        }

        $this->end();
    }

    public function ShowConfig($config, $user_config) {
        $this->begin();
        echo '<h1>Configuration</h1>';
        echo '<h2>Current configuration</h2>';
        foreach ($config as $key => $value) {
            echo "<b>".htmlentities($key)."</b>: <code>".htmlentities($value)."</code><br />";
        }

        echo '<h2>User configuration</h2>';
        // Show input form with the user configuration options to allow the user to change them
        echo '<form method="post" action="/update-config">';
        $apns_environment = $user_config['apns_environment'] ?? '';
        echo '<label for="apns_environment">APNS environment:</label><br />';
        echo '<input type="radio" id="apns_environment" name="apns_environment" value="" '.($apns_environment == '' ? 'checked' : '').'> Default<br />';
        echo '<input type="radio" id="apns_environment" name="apns_environment" value="sandbox" '.($apns_environment == 'sandbox' ? 'checked' : '').'> Sandbox<br />';
        echo '<input type="radio" id="apns_environment" name="apns_environment" value="production" '.($apns_environment == 'production' ? 'checked' : '').'> Production<br />';

        /*
        $some_other_option = $user_config['some_other_option'] ?? '';
        echo '<label for="some_other_option">Some other option:</label><br />';
        echo '<input type="text" id="some_other_option" name="some_other_option" value="'.htmlentities($some_other_option).'"><br />';
        */

        echo '<br />';
        echo '<input type="submit" value="Update configuration">';
        echo '</form>';

        $this->end();
    }
}


