<?php

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;

/** @internal includes */
require_once('Tiqr/Message/Abstract.php');

/**
 * Apple Push Notification Service message class for the HTTP/2 api.push.apple.com APNs API.
 */
class Tiqr_Message_APNS2 extends Tiqr_Message_Abstract
{
    /**
     * Send message.
     */
    public function send()
    {
        $version_info = curl_version();
        if ($version_info['features'] & CURL_VERSION_HTTP2 == 0) {
            throw new RuntimeException('APNS2 requires HTTP/2 support in curl');
        }

        // Get the UID from the client certificate we use for authentication, this
        // is set to the bundle ID.
        $options=$this->getOptions();
        $cert_filename = $options['apns.certificate'];
        $cert_file_contents = file_get_contents($cert_filename);
        if (false === $cert_file_contents) {
            throw new RuntimeException(
                sprintf('Error reading APNS client certificate file: "%s"', $cert_filename)
            );
        }

        $cert=openssl_x509_parse( $cert_file_contents );
        if (false === $cert) {
            throw new RuntimeException('Error parsing APNS client certificate');
        }
        $bundle_id = $cert['subject']['UID'] ?? NULL;
        if (NULL === $bundle_id) {
            throw new RuntimeException('No uid found in the certificate subject');
        }
        $this->logger->info(sprintf('Setting bundle_id to "%s" based on UID from certificate', $bundle_id));
        $this->logger->info(
            sprintf('Authenticating using certificate with subject "%s" valid until "%s"',
                $cert['name'],
                date(DATE_RFC2822, $cert['validTo_time_t'])
            )
        );

        $authProviderOptions = [
            'app_bundle_id' => $bundle_id, // The bundle ID for app obtained from Apple developer account
            'certificate_path' => $cert_filename,
            'certificate_secret' => null // Private key secret
        ];

        $authProvider = AuthProvider\Certificate::create($authProviderOptions);

        // Create the push message
        $alert=Alert::create();
        $alert->setBody($this->getText());
        // Note: It is possible to specify a title and a subtitle: $alert->setTitle() && $alert->setSubtitle()
        //       The tiqr service currently does not implement this.
        $payload=Payload::create()->setAlert($alert);
        $payload->setSound('default');
        foreach ($this->getCustomProperties() as $name => $value) {
            $payload->setCustomValue($name, $value);
        }
        $this->logger->debug(sprintf('JSON Payload: %s', $payload->toJson()));
        $notification=new Notification($payload, $this->getAddress());
        // Set expiration to 30 seconds from now, same as Message_APNS
        $now = new DateTimeImmutable();
        $expirationInstant=$now->add(new DateInterval('PT30S'));
        $notification->setExpirationAt($expirationInstant);

        // Send the push message
        $client = new Client($authProvider, $options['apns.environment'] == 'production');
        $client->addNotification($notification);
        $responses=$client->push();
        if ( sizeof($responses) != 1) {
            $this->logger->warning(sprintf('Unexpected number responses. Expected 1, got %d', sizeof($responses)) );
            if (sizeof($responses) == 0) {
                $this->logger->warning('Could not determine whether the notification was sent');
                return;
            }
        }
        /** @var \Pushok\Response $response */
        $response = reset($responses);  // Get first response from the array
        $deviceToken=$response->getDeviceToken() ?? '';
        // A canonical UUID that is the unique ID for the notification. E.g. 123e4567-e89b-12d3-a456-4266554400a0
        $apnsId=$response->getApnsId() ?? '';
        // Status code. E.g. 200 (Success), 410 (The device token is no longer active for the topic.)
        $statusCode=$response->getStatusCode();
        $this->logger->info(sprintf('Got response with ApnsId "%s", status %s for deviceToken "%s"', $apnsId, $statusCode, $deviceToken));
        if ( strcasecmp($deviceToken, $this->getAddress()) ) {
        $this->logger->warning(sprintf('Unexpected deviceToken in response. Expected: "%s"; got: "%s"', $this->getAddress(), $deviceToken));
        }
        if ($statusCode == 200) {
            $this->logger->notice(sprintf('Successfully sent APNS2 push notification. APNS ID: "%s"; deviceToken: "%s"', $apnsId, $deviceToken));
            return;
        }

        $reasonPhrase=$response->getReasonPhrase(); // E.g. The device token is no longer active for the topic.
        $errorReason=$response->getErrorReason(); // E.g. Unregistered
        $errorDescription=$response->getErrorDescription(); // E.g. The device token is inactive for the specified topic.

        $this->logger->error(sprintf('Error sending APNS2 push notification. APNS ID: "%s"; deviceToken: "%s"; Error: "%s" "%s" "%s"', $apnsId, $deviceToken, $reasonPhrase, $errorReason, $errorDescription));
        throw new RuntimeException(
            sprintf('Error sending APNS2 push notification. Status: %s. Error: "%s" "%s" "%s"', $statusCode, $reasonPhrase, $errorReason, $errorDescription)
        );
    }
}
