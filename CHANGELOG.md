# Changelog
As of release 2.0.0 we started keeping the CHANGELOG.md file. The older entries are copy pasted from the Github release page.

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

