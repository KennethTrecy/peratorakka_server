[![Backend Tests](https://img.shields.io/github/actions/workflow/status/KennethTrecy/peratorakka_server/back-end.yml?style=for-the-badge)](https://github.com/KennethTrecy/peratorakka_server/actions/workflows/back-end.yml)
![GitHub lines](https://img.shields.io/github/license/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/KennethTrecy/peratorakka_server?style=for-the-badge&display_name=tag&sort=semver)
![GitHub closed issues count](https://img.shields.io/github/issues-closed/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub pull request count](https://img.shields.io/github/issues-pr-closed/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub code size in bytes](https://img.shields.io/github/repo-size/KennethTrecy/peratorakka_server?style=for-the-badge)

# Peratorakka Server
A server for any Peratorakka client.

Peratorakka is a software system aimed to manage and track the finances, personal or commercial. It
is made up of a [client] and server. In this repository, it has an implementation of the server which
process the data and stores them in any compatible database. The database may be an instance of
PostgreSQL server, MySQL server, or others as long as it is supported by CodeIgniter framework (it
is framework that Peratorakka server builds on).

There is no public server available right now, but it may happen in the future when the author has
enough time. Yet, I still outlined the instructions below in case someone wants to host an instance
of Peratorakka server.

For more information on the abilities of the system, please read the [author's post].

## Origin
Some parts of the repository was based from [`filled_composer_json`] branch of [Web Template].

The template has been specialized for back-end development.

Other parts were auto-generated using the command `composer create-project codeigniter4/appstarter`.
Therefore, any changes in [composer-installable app starter development repository] should be updated if
necessary.

## Usage

### Installation
The installation steps may be a bit technical and requires admins to be knowledgeable on setting environment variables.

#### Server Requirements
PHP version 7.4 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library

#### Instructions (if you want dedicated server)
1. Copy `.env.lax.example` to `.env` and tailor the configuration for your machine, specifically the `baseURL` and
   any database settings.
2. Run `composer install --no-dev`. Install dependencies for production.
3. Run `composer run migrate:all`. It is recommended to run the command every update.

#### Instructions (if you want containerized server)
Below are instructions to host your application in a container. However, they are not yet clear and
may vary depending on machine and admin's preferences.
1. Copy `env.container.example` to `.env.container` and tailor the configuration for your container.
2. Copy `env` to `.env` and tailor the configuration for your server, specifically the `baseURL` and
   any database settings. Note that `.env` is for the server *inside* the container while `.env.container` is for the container itself.
3. Use `host.docker.internal` for hostname to connect the database server and HTTP server correctly.
4. Run `docker compose --env-file .env.container up --detach`.
4. Run `docker compose --env-file .env.container up --detach --build` if you want to rebuild the
   HTTP server after receiving updates by using `git pull origin master`.

### Initialization (for developers)
If you want to contribute, the repository should be initialized to adhere in [Conventional Commits
specification] for organize commits and automated generation of change log.

#### Prerequisites
- [Node.js environment]
- [pnpm] (optional)

#### Instructions
1. By running the command below, all your commits will be linted to follow the [Conventional Commits
specification].
   ```
   $ npm install
   ```

   Or if you have installed [pnpm], run the following command:
   ```
   $ pnpm install
   ```
2. To generate the change log automatically, run the command below:
   ```
   $ npx changelogen --from=[tag name or branch name or commit itself] --to=master
   ```

### Syncing application space
To synchronize the files in this repository's history from the framework's application space:
1. Reset/rebase the `master` branch on any desired branch.
2. Run `Copy-Item vendor/codeigniter4/framework/[path to the updated file] [path to the old file on your root]`.

## Notes

### License
The repository is licensed under [MIT].

### Want to contribute?
Please read the [contributing guide] for different ways to contribute in the project.

You can also make a financial contribution, no matter how small, to support its development and maintenance.

[![Donate badge](https://img.shields.io/badge/PayPal-_?logo=paypal&label=Donate%20via&color=%23003087&link=https%3A%2F%2Fpaypal.me%2FKennethTrecy)](https://www.paypal.me/KennethTrecy)

### Author
Coded by Kenneth Trecy Tobias.

### Disclaimer
This personal project may contain references to trademarks, which are included in good faith. However, it is important to note that such references do not indicate any endorsement, affiliation, or sponsorship by the respective trademark holders unless explicitly stated.

[`filled_composer_json`]: https://github.com/KennethTrecy/web_template/tree/filled_composer_json
[Web Template]: http://github.com/KennethTrecy/web_template
[composer-installable app starter development repository]: https://github.com/codeigniter4/CodeIgniter4
[intl]: http://php.net/manual/en/intl.requirements.php
[mbstring]: http://php.net/manual/en/mbstring.installation.php
[mysqlnd]: http://php.net/manual/en/mysqlnd.install.php
[libcurl]: http://php.net/manual/en/curl.requirements.php
[MIT]: https://github.com/KennethTrecy/web_template/blob/master/LICENSE
[Node.js environment]: https://nodejs.org/en/
[pnpm]: https://pnpm.io/installation
[Conventional Commits specification]: https://www.conventionalcommits.org/en/v1.0.0/
[contributing guide]: ./CONTRIBUTING.md
[client]: https://github.com/KennethTrecy/peratorakka_client
[author's post]: https://www.linkedin.com/posts/kenneth-trecy-tobias_good-day-everyone-after-five-months-of-testing-activity-7134037085828616192-Xtvx
