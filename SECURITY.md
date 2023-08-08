# Security

## OCRA suite

The default OCRA Suite (RFC 6287) that is used by Tiqr is `OCRA-1:HOTP-SHA1-6:QH10-S`
This basically calculates the HMAC-SHA1 (`HOTP-SHA1`) over a buffer with:
- `QH10` A 10 hex digit (40-bits) long challenge
- `S` authentication session information of max 64 hex digits (256-bits), by default tiqr uses 32 hex digits (128-bits) of those 64 for the session identifier and the rest of the buffer is padded with zero's.
- The client's secret key, Tiqr uses 64 hex digits (256-bits) keys

Then from the calculated `HMAC-SHA1`, a `6` decimal digit long response is calculated

The OCRA suite is configurable. Note that the client and server must agree on the OCRA suite that is used. The current Tiqr clients for ios and Andoid store the OCRA suit during the enrollment of an account in the account, it cannot be changed later.

### HMAC algorithm choice
There are security concerns when using SHA-1 for digital signatures of today. "As of today, there is no indication that attacks on SHA-1 can be extended to HMAC-SHA-1." (RFC6194 3.3) However, for new implementations using a hash function from the SHA-2 family should be considered e.g. "OCRA-1:HOTP-SHA256-6:QH10-S".
Currently `SHA-1`, `SHA-256` and `SHA-512` are supported.

### Response length
A Tiqr authentication consists of both the client and server calculating the response given the secret key, challenge question and the session information and then the server comparing the response from the client with its own response. In the default OCRA suite the reponse is six (6) decimal digits (0-9) long. This means that a client has a 1 in 10^6 chance of guessing the right response in one try. This is a tradeoff between having responses that a user can easily copy during offline authentication and resistance against guessing. When using responses of this length, the application must implement anti-guessing counter-measures, e.g. rate limiting the number of attempts that a client can make, locking an account after N-tries or increasing the response length in the OCRA suite. E.g. "OCRA-1:HOTP-SHA256-8:QH10-S" uses an 8 digit response.

Also consider whether an attacker can easily guess and acces the authentication to Tiqr accounts (e.g. user id's are known / guessable) and so try many different accounts. For attacker the chances of having success, without countermeasures, for 10,000 tries for one account, or 1 try for 10,000 accounts are the same.

When using the default response length of 6, the chances of correctly guessing a 6 digit response code in N tries is N / 10^6:

> N=1: 1/10^6 = 0.0001%; N=2: 0.0002%; N=3: 0.0003%; N=10: 0,0010; N=100: 0,01%; N=1000: 0,1%, N=10000: 1%

## Tiqr client PIN
The current ios and Android Tiqr clients by SURF protect the OCRA secret with a four digit PIN. Entering a PIN for a tiqr account is mandatory during enrollment.

The Tiqr client stores there OCRA secret encrypted by the PIN in such a way that each possible PIN will result in a OCRA secret so that an attacker that is able to retrieve the encrypted OCRA secret cannot test what is a valid PIN. This scheme relies on the server validating the response, and by that validating the PIN. To prevent a guessing attack on the PIN of a user's account, the server must lock the account after a few consecutive invalid responses have been submitted (e.g. 3). It is on the server to implement this protection.

For a 4 digit PIN, the chances of correctly guessing a 4 digit response code ofter N tries is N / 10^4:

> N=1: 1/10^4 = 0.01%; N=2: 0.02%; N=3: 0.03%; N=10: 0,1%; N=100: 1%; N=1000: 10%, N=10000: 100%

## Keeping the OCRA Secrets secret

The security of Tiqr relies on the OCRA secret being secret. It is trivial to calculate the response when the OCRA secret is known.

## Session keys and OCRA session information
Session keys are used in multiple places during authentication and enrollment and are generated using the PHP random_bytes() function, which generates cryptographically secure random bytes. The library uses keys with SESSION_KEY_LENGTH_BYTES of entropy, currently this is set to 16 bytes (128 bits). The session keys are HEX encoded, so a 16 byte key (128 bits) will be 32 characters long.

Considerations:
* Uniqueness - We guarantee uniqueness by using a sufficiently number of bytes, by using 16 bytes (128 bits) we can expect a collision after having generated 2^64 IDs. This more than enough for our purposes, the session keys in the tiqr protocol are not persisted and have a lifetime of no more than a few minutes.
* Unpredictability - It must be infeasible for an attacker to predict or guess session keys during enrollment 128 bits should be sufficiently long for this purpose because of the short lifetime of these keys
* OCRA session - A session key is used as session information in the OCRA authentication. Even if the session keys, challenges and the correct responses of many authentications are known to an attacker it should still be infeasible to get the user secret as that is equivalent to reversing a HMAC-SHA1 of a string the length of the secret (32 bytes - 2^256 possibilities for a typical tiqr client implementation). Previously 32 byte OCRA secrets (64 hex digits long) have been used. However, 16 bytes should be more than enough. Using 32 bytes makes the QR codes bigger, because both for authentication and enrollment a session key is embedded in the uri that is encoded in the QR code. 

## OCRA challenge question
In Tiqr the authentication contains both a challenge question (default 10 hex digits, 40 bits) and session information (128 bits). These play the same role when calculating a response. The 128 bit "challenge" provided by the session information, without taking the 40 bit challenge question into account, by itself would already be sufficient.
