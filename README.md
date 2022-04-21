# yubiKey exercise
An `U2F` authentication by hardware keys exercise

Hardware keys :closed_lock_with_key: also called `FIDO security keys` are yet another solution to verify the user who is trying to log-in.  
An example of a key :key: is this one manufactured by Yubico  
![YubiKey 5 NFC](/static/images/yubiKey_min.png "YubiKey 5 NFC - a hardware u2f verification key")  

Training `U2F` authentication by hardware keys is the main purpose of this repo  
and because of this, `SOLID`, "magic numbers" etc. are not the most important concern.  
Obsolete in normal circumstances (in production) logs like `console.log`'s, `print`'s etc. will be used also.  

It will include more independent sub-projects or tasks related to yubiKey `U2F` development.

## Dependency & Resources
Codes in this exercise are based on Yubico [php-u2flib-server](https://github.com/Yubico/php-u2flib-server) repo.  
Some parts are copy-pased from their repo with slight modification (I do not own copyright on the original codes).  

To learn more about `U2F` development, yubiKeys and things around `FIDO`, visit [developers.yubico.com/U2F](https://developers.yubico.com/U2F/) or [FIDO Alliance website](https://fidoalliance.org/).

## Requirements
`Apache`, `mySQL`, `PHP` are required.  

To run `U2F` authentication locally (`localhost`) *SSL* keys are required (`https://`).  
Solution to create keys (or one of solutions) which met requirements on Linux with `Apache2` (2.4.x) is like this:
1. Enable *SSL* `a2enmod ssl`
2. Go to `/etc/apache2/`
3. Create folder to keep keys, e.g. `ssl_keys` and go to this folder
4. Create key for one year with `RSA 2048` (it's `localhost` so security is not cause for concern)  
    `openssl genrsa -out localhost.key 2048`  
    `openssl req -new -out localhost.csr -sha256 -key localhost.key`  
    `openssl x509 -req -in localhost.csr -days 365 -signkey localhost.key -out localhost.crt -outform PEM`
5. `localhost.crt` has to be public (private key `localhost.key` should remain private :wink:)  
    `chmod 644 localhost.crt`
6. Go to `/etc/apache2/sites-available`, edit `default-ssl.conf` and change lines with `SSLCertificateFile` and `SSLCertificateKeyFile` into  
   ```
   SSLCertificateFile    /etc/apache2/ssl_keys/localhost.crt
   SSLCertificateKeyFile  /etc/apache2/ssl_keys/localhost.key
   ```
7. It might be required to add an alias into `default-ssl.conf` like:
    ```
    Alias /alias/ "/home/path-to-account-directory/public_html/"
    <Directory "/home/path-to-account-directory/public_html/">
        # your settings, e.g.:
        Options Indexes FollowSymlinks MultiViews
        AllowOverride all
        Order allow,deny
        allow from all
    </Directory>
    ```

Another requirement is a `local_settings.php` file with database settings like e.g.:
```php
<?php
$db = array(
	'hostname' => 'localhost',
	'username' => 'admin',
	'password' => 'password1',
	'database' => 'db',
);
```
To prepare `mySQL` database, `mysql_scheme.sql` has to be imported. To do this on Linux:
1. Create database, e.g. `database_name`
2. Type in Linux console: `mysql -u root database_name < mysql_scheme.sql`
3. Grant privileges for the user of database:
    `GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'hostname';`

## Quick start
To run php version just type `https://localhost/<path-to-Apache-server>/yubiKey_exercise/` in a browser.

