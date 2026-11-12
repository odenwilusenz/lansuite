# Docker Environment Setup 

## Pre-Steps

* Create a copy of mysql.env.dist and name it mysql.env
* Fill out the default configuration variables

## Start environment

```
docker-compose up -d
```

## Access Environment

* Open a browser and open url localhost:8080
* A wizard should be prompted 
* Use as db server "mysqldb"

## Internet mail

The PHP image does not include Sendmail, so PHP `mail()` cannot deliver messages.
Enable SMTP under Installation → module Mail (host, port, STARTTLS, SMTPS or no encryption, username and password).
The sender address is the party contact mail. The SMTP password is not shown again.
Its encryption key is written to `inc/base/config.php` and must stay out of git.
Admins can send a test message from Mail → Testmail.