# TiqrTestServer

This is a testserver with minimal dependencies that is intended for testing
enrollement and authentication with a tiqr client.

## Requirements
The TestSever uses the PHP cli build-in webserver. It can be run directly from the command
line if you have PHP installed. Supported PHP versions are 7.2 and 7.4. Alternatively a 
Dockerfile.testserver is included to run the TestServer from a container. To enroll and authenticate with the TestServer 
you need a tiqr client (e.g. one of the clients from https://tiqr.org), the TestServer does not include a tiqr client. 

## Configuration
The first step is configuring the TestServer for the Tiqr clients that you want to test.

Copy the included `TestServer/config/config.dist` to `TestServer/config/config` and set the appropriate values:
* `host_url ` — The URL given here is used a basis for generating the URLs that the Tiqr client (i.e. the iPhone or 
  Android phone app) will use to contact the test server. E.g. "http://my-macbook.local:8000"
* `tiqrauth_protocol` — This is the Custom URL scheme that is used by the app for authentications.
  Do not add the '://' part. E.g. the tiqr.org production client uses 'tiqrauth'. This must match the configuration of 
  the tiqr client that is used, otherwise the tiqr client will not accept the authentication QR code and push notification.
* `tiqrenroll_protocol` — This is the Custom URL scheme that is used by the app for enrolling new user accounts.
  Do not add the '://' part. E.g. the tiqr.org production client uses 'tiqrenroll'. This must match the configuration of
  the tiqr client that is used, otherwise the tiqr client will not accept the authentication QR code.
* `token_exchange_url` – Optional. The URL of the token exchange server to use. Required for sending push notifications 
  of types APNS and FCM.
* `token_exchange_appid` – Optional. The ID of the application at the token exchange server. Required for sending push
  notifications of types APNS and FCM. 
* `apns_certificate_filename` – Optional. The filename (relative to the config directory) of the file with the Apple push 
  notification client certificate and private key (unencrypted) in PEM format. Required for sending push notifications to 
  iOS devices.
* `apns_environment` – Optional. The apple push notification environment to use: "production" or "sandbox".
* `firebase_apikey – Optional. The Google firebase API key. Required for sending push notifications to Android devices.

## Installation
The TestServer can be run from a Docker container or can be run using the PHP build-in webserver 

### Docker
* Create a docker image. This image will include the configuration in the config directory.
```
docker build -f "Dockerfile.testserver" -t tiqr-testserver:latest "."
```
* Run the docker image:
```
docker run -it -p 8000:8000/tcp tiqr-testserver:latest
```
It is useful to run the container interactively (-it) so see the log messages that the testserver outputs.  

Browse to http://localhost:8000 to use the testserver

### Local

#### Requirements
* PHP 7.2 or 7.4 with
  * php-gd extention
  * php-curl extention
* composer

#### Install
* Use `composer install` from the root of this repository to install the dependencies

#### Run
Start the TestServer from the TestServer directory in this repository using the buildin
PHP webserver:
```
php -S 0.0.0.0:8000 app.php
```
Browse to http://localhost:8000 to use the testserver


# Using the TestServer from physical devices
When using the TestServer from an app on a physical phone, the phone needs to be able to contact
the TestServer on the URL specified

## iPhone to OSX
On OSX with an iPhone device you can use the `.local` domain of the mac. This is reachable 
from an iPhone over Wi-Fi or when it is connected via USB to OSX. This relies on using mDNS. 
You find the .local host name to use under `Computer Name` in "Properties->Sharing" after 
`Computers on your local network can access your computer at:`
E.g. with Computer name `my-macbook.local` you set the `host_url` in the TestServer config to `http://my-macbook.local:8000`.
From your iOS device you should be able to open this URL in safari and contact the TestServer.

## Android
For Android devices you can create a tunnel using `adb` from the Android SDK platform-tools to make 
localhost available on the phone.
E.g. when using `adb reverse tcp:8000 tcp:8000`, you set the `host_url` in the TestServer config to `http://localhost:8000`.
From your Android device you should be able to open this URL in its browser and contact the TestServer.

## Other
Lookup the IP of the PC on the network shared with the device and set that as the host
url. E.g. `http://192.0.2.2`
