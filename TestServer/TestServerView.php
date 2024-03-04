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
<a href="/start-enrollment">Enroll new user</a><br /><br />
<a href="/start-authenticate">Authenticate user</a><br /><br />
<a href="/list-users">list users</a><br /><br />
HTML;
        $this->end();
    }

    public function ListUsers($users) {
        $this->begin();
        echo <<<HTML
<h1>List of users</h1>
<p>This is the list of user IDs that are registered on this server. Click a user ID to start an authentication for that user.</p>
<table border="1">
<tr>
    <th>userId</th>    
    <th>displayName (version | User-Agent)</th>
    <th>notificationType</th>
    <th>notificationAddress</th>
    <th>secret</th>
</tr>
HTML;
        foreach ($users as $user) {
            $user['userId'] = $user['userId'] ?? '—';
            $user['displayName'] = $user['displayName'] ?? '—';
            $user['notificationType'] = $user['notificationType'] ?? '—';
            $user['notificationAddress'] = $user['notificationAddress'] ?? '—';
            $user['secret'] = $user['secret'] ?? '—';
            echo <<<HTML
<tr>
    <td><a href="/start-authenticate?user_id={$user['userId']}"><code>{$user['userId']}</code></a></td>
    <td><code>{$user['displayName']}</code></td>
    <td><code>{$user['notificationType']}</code></td>
    <td><code>{$user['notificationAddress']}</code></td>
    <td><code>{$user['secret']}</code></td>
</tr>
HTML;
        }
        echo "</table>";
        $this->end();
    }

    public function StartEnrollment($enroll_string, $image_url) : void {
        $this->begin();
        echo <<<HTML
<h1>Enroll a new user</h1>
<p>Scan the QR code below using the Tiqr app. When using the smart phone's browser you can tap on the QR code to open the link it contains.</p>
<p>You can use this QR code only once.</p>
<a href="$enroll_string"><img src="$image_url" /></a> <br />
<br />
<code>$enroll_string</code>
<br />
<br />
<a href="/start-enrollment">Refresh enrollemnt QR code</a><br />
HTML;
        $this->end();
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
</body>
</html>
HTML;
    }

    public function StartAuthenticate(string $authentication_URL, string $image_url, string $user_id, string $response, string $session_key)
    {
        $refreshurl = '/start-authenticate';
        if (strlen($user_id) > 0) {
            $refreshurl.= "?user_id=$user_id";
        }
        $this->begin();
        echo <<<HTML
        <h1>Authenticate user $user_id</h1>
<p>Scan the QR code below using the Tiqr app. When using the smart phone's browser you can tap on the QR code to open the link it contains.</p>
<a href="$authentication_URL"><img src="$image_url" /></a> <br />
<br />
<code>$authentication_URL</code>
<br />
HTML;
        if (strlen($response)>0) {
            echo <<<HTML
<p>The response (for offline validation) is: <code>$response</code></p>
<p><a href="/send-push-notification?user_id=$user_id&session_key=$session_key">send push notification to the user</a></p>
HTML;

        }
        echo <<<HTML
<br />
<a href="$refreshurl">Refresh authentication QR code</a><br />
HTML;
        $this->end();
    }


    function PushResult(string $notificationresult) {
        $this->begin();
        $text = htmlentities($notificationresult);
            echo <<<HTML
<p>$text</p>
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


}


