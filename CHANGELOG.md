# Changelog
As of release 2.0.0 we started keeping the CHANGELOG.md file. The older entries are copy pasted from the Github release page.

## 2.1.0
The release adds support for sending push notifications without using a token exchange. Added checks for invalid input 
to the default (v2) OCRA implementation. No interface changes.

**Features**
* Add two new message types `APNS_DIRECT` and `FCM_DIRECT` to `Tiqr_Service::sendAuthNotification` that do not do
  a lookup of the notificationAddress at the token exchange, instead the notificationAddress is used to send a
  notification directly to the device.

* Add more input validation to the default (v2) OCRA implementation. More methods in the `OCRA` and
  `Tiqr_OCRAWrapper` classes can now throw exceptions. Added the One-Way Challenge Response test vectors from the RFC
  to the unit tests.

**Bugfix**
* Fix bug in the OCRA v2 algorithm that computed responses that did not match the RFC reference 
  implementation when to OCRA suite included a password component that contained an "S" (e.g. PSHA1).
  This does not affect the Tiqr app because password components are not used there. 


## 2.0.0
A release with several backward compatibility breaking changes. Most notable are:

1. User and User Secret storage are no longer intertwined. You are now required to create both, the user storage factory no longer creates a user secret storage for you when you have not configured it.
2. Serveral of the Tiqr server library services now require a PSR style logger to function correctly.
3. LDAP support was dropped from the project. If you used it, sorry we no longer ship it as of version 2

Behavioral changes:

1. The code now throws exceptions when unrecoverable runtime issues are encountered. Previously the service would return a 'error-ish' response like null or false. We now throw exceptions in these situations.
2. As mentioned above in the BC breaking changes: the User storage situation changed. More info can be found:  #30 and `https://www.pivotaltracker.com/story/show/181525762` 

**Features**
* Implement and with it, improve logging #27
* Add a test server for mobile app development #21
* Improve StateStorage File implementation #35
* Throw exceptions when unrecoveral error situation occur #36
* Move expiry action and make probability of triggering it configurable #25
* State storage pdo expiry #20
* Convert TravisCI to GitHub Actions #34

**Bugfix**
* Fix OCRA algorithm used by tiqr "v2" calculating incorrect responses for some values #22
* Remove C2MD and GCM Message API support #31
* Demystify user storage #30

* **Other chores and tasks**
* Remove unreachable code #32
* Log the http-statuscode and error received from firebase #14
* Run local-php-security-checker on Travis CI #28
* Update PHP version for phpunit #26
* Enable code coverage on unit tests #18
* Remove unused LDAP storage solution #29
* State storage updates #16
* Fix travis builds on php7.2 #15

## 1.1.12
Add exta logging to FCM errors

## 1.1.10
Add firebase FCM push notifications

## 1.1.1
updated dependency for phpqrcode lib

## 1.1
maintenance update
* Add OATHservice backend
* Migrate from C2DM to GCM libraries for push notifications on Android
* Add ability to use different QR code generation libraries
* Bugfixes

