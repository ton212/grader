# Grader (a.k.a. Online Judge)

Usually I would name my project something with name from anime character, but I feel I want to keep the name I chosen for future project, so it is just "grader" for the time.

My grader currently supports PHP, Python 2/3 (both!), Ruby, C, C++, C# and Java. Problem input generator can be written in Python 2, PHP and Java.

The grading backend use a seperate PHP process and use [Docker](http://docker.io) to isolate unsafe code.

## Installation

I tried vagrant and puppet which lives in `vagrant` directory. It doesn't install MySQL properly and I stopped working on it and you if you fix it, please make it install supervisord and configuration.

Only tested (and should only work on) Ubuntu 13.04. (12.04 may work, I think)

**Manual installation:** (the only way for now)  Note that this should be run only on empty machine.

1. Setup nginx repository: http://wiki.nginx.org/Install
2. Install dependencies: 
```sh
sudo apt-get install php5-cli php5-mysqlnd php5-fpm nginx beanstalkd supervisor
```
3. Install composer: 
```sh
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```
4. Install MariaDB: https://downloads.mariadb.org/mariadb/repositories/
5. Install Docker: http://docs.docker.io/en/latest/installation/ubuntulinux/#ubuntu-raring
6. Move files into target: 
```sh
sudo mkdir /var/www/ /var/grader/
sudo chown www-data:www-data /var/www
sudo cp -r assets server templates index.html /var/www/
sudo cp -r vm/graderclient/ /var/grader/
```
(From this point it is assumed that you run all commands in the project's root directory)
7. Build Docker images: 
```sh
cd vm
sudo ./build-raring.sh
sudo rm -r raring
sudo docker build -t=grader -rm=true .
cd ..
```
8. *(optionally)* Cleanup:
```sh
sudo docker rm `sudo docker ps -aq`
```
9. Setup nginx: 
```sh
sudo cp vagrant/nginx.conf /etc/nginx/sites-available/default
sudo service nginx restart
```
10. Config grader by editing following files
   - `server/database.php`
   - Copy `server/opauth.sample.php` to `server/opauth.php` and edit `security_salt` and `key` to two random strings, fill keys for social networks. ([Register new app on Facebook](https://developers.facebook.com/apps), [Register new app for Google+](https://cloud.google.com/console), [Register new app on Twitter](https://dev.twitter.com/apps/new))
11. Config grader client by editing following files
   - Copy `/var/grader/config.default.php` to `/var/grader/config.php` and set the `setKey` argument to the same as `key` in `opauth.php`, set `setGuzzle` argument to URL of grader web (try `curl`-ing the URL first)
12. Setup beanstalkd by editing `/etc/default/beanstalkd`. Enable service starting, optionally add persistent and run `sudo service beanstalkd start` to apply.
13. *(optionally)* Enable supervisord web interface: http://supervisord.org/configuration.html#inet-http-server-section-settings
14. Config supervisord:
```sh
sudo cp vagrant/supervisord.conf /etc/supervisor/conf.d/grader.conf
sudo service supervisor restart
```
15. Create database
```sh
cd /var/www/server/
php schema.php
```
16. Grader is now installed

## ACL

Grader use a whitelist-based ACL. The default is to **deny** all access so you probably need to open it up first. To do this, insert row into the `acls` database:

- `user_id`: 0 for everyone (including guests)
- `object`: put `tests` here.
- `object_id`: 0 for every object, or test id.
- `acl`: One of `view` (see and submit to test), `add` (create new tests), `edit` (add problems, view submissions code made by other users, view levenshtein distance between submissions, submit even the test is readonly), `delete` (doesn't do anything yet)

Note that if even if you allow guests to `view` tests, they still can't submit.

When you create a test, an ACL to `view`,`edit`,`delete` is created for the creator.

## License

I apologies for proper licensing in every files. Anyway, you can use this under [AGPLv3](https://www.gnu.org/licenses/agpl-3.0.html) or later version.

Of course, your problem statements and input/output code does not need to follow the license's requirement.

