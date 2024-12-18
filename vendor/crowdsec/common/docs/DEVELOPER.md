![CrowdSec Logo](images/logo_crowdsec.png)
# CrowdSec PHP common

## Developer guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Local development](#local-development)
  - [DDEV setup](#ddev-setup)
    - [DDEV installation](#ddev-installation)
    - [Prepare DDEV PHP environment](#prepare-ddev-php-environment)
  - [DDEV Usage](#ddev-usage)
    - [Use composer to update or install the lib](#use-composer-to-update-or-install-the-lib)
    - [Unit test](#unit-test)
    - [Coding standards](#coding-standards)
- [Commit message](#commit-message)
  - [Allowed message `type` values](#allowed-message-type-values)
- [Update documentation table of contents](#update-documentation-table-of-contents)
- [Release process](#release-process)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->



## Local development

There are many ways to install this library on a local PHP environment.

We are using [DDEV](https://ddev.readthedocs.io/en/stable/) because it is quite simple to use and customize.

Of course, you may use your own local stack, but we provide here some useful tools that depends on DDEV.


### DDEV setup

For a quick start, follow the below steps.


#### DDEV installation

For the DDEV installation, please follow the [official instructions](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/).


#### Prepare DDEV PHP environment

The final structure of the project will look like below.

```
crowdsec-common-dev-project (choose the name you want for this folder)
│       
│
└───.ddev
│   │   
│   │ (DDEV files)
│   
└───my-code (do not change this folder name)
    │   
    │
    └───common (do not change this folder name)
       │   
       │ (Clone of this repo)
         
```

- Create an empty folder that will contain all necessary sources:
```bash
mkdir crowdsec-common-dev-project
```

- Create a DDEV php project:

```bash
cd crowdsec-common-dev-project
ddev config --project-type=php --php-version=8.2 --project-name=crowdsec-php-common
```

- Add some DDEV add-ons:

```bash
ddev get julienloizelet/ddev-tools
```

- Clone this repo sources in a `my-code/common` folder:

```bash
mkdir -p my-code/common
cd my-code/common && git clone git@github.com:crowdsecurity/php-common.git ./
```

- Launch DDEV

```bash
cd .ddev && ddev start
```
This should take some times on the first launch as this will download all necessary docker images.

### DDEV Usage


#### Use composer to update or install the lib

Run:

```bash
ddev composer update --working-dir ./my-code/common
```

#### Unit test

```bash
ddev php ./my-code/common/vendor/bin/phpunit  ./my-code/common/tests/Unit --testdox
```


#### Coding standards

We set up some coding standards tools that you will find in the `tools/coding-standards` folder.
In order to use these, you will need to work with a PHP version >= 7.4 and run first:

```bash
ddev composer update --working-dir=./my-code/common/tools/coding-standards
```

##### PHPCS Fixer

We are using the [PHP Coding Standards Fixer](https://cs.symfony.com/)

With ddev, you can do the following:


```bash
ddev phpcsfixer my-code/common/tools/coding-standards/php-cs-fixer ../

```

##### PHPSTAN

To use the [PHPSTAN](https://github.com/phpstan/phpstan) tool, you can run:


```bash
ddev phpstan /var/www/html/my-code/common/tools/coding-standards phpstan/phpstan.neon /var/www/html/my-code/common/src

```


##### PHP Mess Detector

To use the [PHPMD](https://github.com/phpmd/phpmd) tool, you can run:

```bash
ddev phpmd ./my-code/common/tools/coding-standards phpmd/rulesets.xml ../../src
```

##### PHPCS and PHPCBF

To use [PHP Code Sniffer](https://github.com/squizlabs/PHP_CodeSniffer) tools, you can run:

```bash
ddev phpcs ./my-code/common/tools/coding-standards my-code/common/src PSR12
```

and:

```bash
ddev phpcbf  ./my-code/common/tools/coding-standards my-code/common/src PSR12
```


##### PSALM

To use [PSALM](https://github.com/vimeo/psalm) tools, you can run:

```bash
ddev psalm ./my-code/common/tools/coding-standards ./my-code/common/tools/coding-standards/psalm
```

##### PHP Unit Code coverage

In order to generate a code coverage report, you have to:


- Enable `xdebug`:
```bash
ddev xdebug
```

To generate a html report, you can run:
```bash
ddev php -dxdebug.mode=coverage ./my-code/common/tools/coding-standards/vendor/bin/phpunit --configuration ./my-code/common/tools/coding-standards/phpunit/phpunit.xml
```

You should find the main report file `dashboard.html` in `tools/coding-standards/phpunit/code-coverage` folder.


If you want to generate a text report in the same folder:

```bash
ddev php -dxdebug.mode=coverage ./my-code/common/tools/coding-standards/vendor/bin/phpunit --configuration ./my-code/common/tools/coding-standards/phpunit/phpunit.xml --coverage-text=./my-code/common/tools/coding-standards/phpunit/code-coverage/report.txt
```

## Commit message

In order to have an explicit commit history, we are using some commits message convention with the following format:

    <type>(<scope>): <subject>

Allowed `type` are defined below.
`scope` value intends to clarify which part of the code has been modified. It can be empty or `*` if the change is a
global or difficult to assign to a specific part.
`subject` describes what has been done using the imperative, present tense.

Example:

    feat(logger): Add a new property for logger


You can use the `commit-msg` git hook that you will find in the `.githooks` folder : 

```
cp .githooks/commit-msg .git/hooks/commit-msg
chmod +x .git/hooks/commit-msg
```

### Allowed message `type` values

- chore (automatic tasks; no production code change)
- ci (updating continuous integration process; no production code change)
- comment (commenting;no production code change)
- docs (changes to the documentation)
- feat (new feature for the user)
- fix (bug fix for the user)
- refactor (refactoring production code)
- style (formatting; no production code change)
- test (adding missing tests, refactoring tests; no production code change)


## Update documentation table of contents

To update the table of contents in the documentation, you can use [the `doctoc` tool](https://github.com/thlorenz/doctoc).

First, install it:

```bash
npm install -g doctoc
```

Then, run it in the documentation folder:

```bash
doctoc docs/* --maxlevel 4  
```


## Release process

We are using [semantic versioning](https://semver.org/) to determine a version number. To verify the current tag, 
you should run: 
```
git describe --tags `git rev-list --tags --max-count=1`
```

Before publishing a new release, there are some manual steps to take:

- Change the version number in the `Constants.php` file
- Update the `CHANGELOG.md` file

Then, you have to [run the action manually from the GitHub repository](https://github.com/crowdsecurity/php-common/actions/workflows/release.yml)


Alternatively, you could use the [GitHub CLI](https://github.com/cli/cli): 
- create a draft release: 
```
gh workflow run release.yml -f tag_name=vx.y.z -f draft=true
```
- publish a prerelease:  
```
gh workflow run release.yml -f tag_name=vx.y.z -f prerelease=true
```
- publish a release: 
```
gh workflow run release.yml -f tag_name=vx.y.z
```

Note that the GitHub action will fail if the tag `tag_name` already exits.


 
