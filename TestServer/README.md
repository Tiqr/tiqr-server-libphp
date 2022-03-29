# TiqrTestServer

This is a testserver with minimal dependencies that is intended for testing tiqr 
enrollement and authentication using QR codes. Sending push notifications is not
supported (yet).

# Requirements

* PHP 7.2
   * php-gd extention
   * php-curl extention
* composer

# Install
1. Use `composer install` from the root of this repository to install the dependencies

# Configuration
Copy `TestServer/config/config.dist` to `TestServer/config/config` and set the appropriate values:
* `host_url ` — The URL given here is the URL that the Tiqr client (i.e. iPhone or Android phone) will
use to contact the server.
* `tiqrauth_protocol` — This is the Custom URL scheme that is used by the app for authentications.
  Do not add the '://' part. E.g. the tiqr.org client uses 'tiqrauth'. This must match the configuration of 
  the tiqr client that is used.
* `tiqrenroll_protocol` — This is the Custom URL scheme that is used by the app for enrolling new user accounts.
  Do not add the '://' part. E.g. the tiqr.org client uses 'tiqrenroll'. This must match the configuration of
  the tiqr client that is used
* `token_exchange_url` – Optional. The URL of the token exchange server to use. Required for sending push notifications.
* `token_exchange_appid` – Optional. The ID of the application at the token exchange server. Required for sending push notifications. 
* `apns_certificate_filename` – Optional. The filename (relative to the config directory) of the file with the Apple push notification 
  client certificate and private key in PEM format. Required for sending push notifications to iOS devices.
* `apns_environment` – Optional. The apple push notification environment to use: "production" or "sandbox".
* `firebase_apikey – Optional. The Google firebase API key. Required for sending push notifications to Android devices.

## Using from physical devices
### iPhone to OSX
On OSX with an iPhone device you can use the `.local` domain of the mac. This is reachable 
from an iPhone over WifI or when it is connected via USB to OSX. This relies on using mDNS. 
You find the .local host name to use under `Computer Name` in "Properties->Sharing" after 
`Computers on your local network can access your computer at:`

### Android
For Android devices you can create a tunnel using `adb` from the Android SDK platform-tools to make 
localhost available on the phone.
E.g. with `adb reverse tcp:8000 tcp:8000` use host URL `http://localhost:8000` 

### Other
Lookup the IP of the PC on the network shared with the device and set that as the host
url. E.g. `http://192.0.2.2`

# Run
Start the TestServer from the TestServer directory in this repository using the buildin 
PHP webserver:
```
php -S 0.0.0.0:8000 app.php
```
Browse to http://localhost:8000 to use the testserver