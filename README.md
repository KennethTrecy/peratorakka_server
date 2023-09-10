[![Backend Tests](https://img.shields.io/github/actions/workflow/status/KennethTrecy/peratorakka_server/backend.yml?style=for-the-badge)](https://github.com/KennethTrecy/peratorakka_server/actions/workflows/backend.yml)
![GitHub lines](https://img.shields.io/github/license/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/KennethTrecy/peratorakka_server?style=for-the-badge&display_name=tag&sort=semver)
![GitHub closed issues count](https://img.shields.io/github/issues-closed/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub pull request count](https://img.shields.io/github/issues-pr-closed/KennethTrecy/peratorakka_server?style=for-the-badge)
![GitHub code size in bytes](https://img.shields.io/github/repo-size/KennethTrecy/peratorakka_server?style=for-the-badge)

# Peratorakka Server
A server for any Peratorakka client.

## Origin
Some parts of the repository was based from [`filled_composer_json`] branch of [Web Template].

The template has been specialized for backend development.

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

#### Instructions
1. Copy `env` to `.env` and tailor for your machine, specifically the baseURL and any database
   settings.
2. Run `composer run migrate:all`.

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
Read the [contributing guide] for different ways to contribute in the project.

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
