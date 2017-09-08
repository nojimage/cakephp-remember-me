# RememberMe authentication adapter plugin for CakePHP 3

<p align="center">
    <a href="LICENSE.txt" target="_blank">
        <img alt="Software License" src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square">
    </a>
    <a href="https://travis-ci.org/nojimage/cakephp-remember-me" target="_blank">
        <img alt="Build Status" src="https://img.shields.io/travis/nojimage/cakephp-remember-me/master.svg?style=flat-square">
    </a>
    <a href="https://codecov.io/gh/nojimage/cakephp-remember-me" target="_blank">
        <img alt="Codecov" src="https://img.shields.io/codecov/c/github/nojimage/cakephp-remember-me.svg?style=flat-square">
    </a>
    <a href="https://packagist.org/packages/nojimage/cakephp-remember-me" target="_blank">
        <img alt="Latest Stable Version" src="https://img.shields.io/packagist/v/nojimage/cakephp-remember-me.svg?style=flat-square">
    </a>
</p>

This plugin provides an authenticate handler that permanent login by cookie. This plugin use method of issuing a token, instead of set to cookie encrypted username/password.

This library inspired by Barry Jaspan's article "[Improved Persistent Login Cookie Best Practice](http://jaspan.com/improved_persistent_login_cookie_best_practice)", and Gabriel Birke's libray "https://github.com/gbirke/rememberme".

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require nojimage/cakephp-remember-me
```

You will need to add the following line to your application's bootstrap.php file:

```
Plugin::load('RememberMe');
```

Run migration:

```
bin/cake migrations migrate -p RememberMe
```

## Usage

In your `AppController` setup AuthComponent:

```(php)
public function initialize()
{
    // ... snip

    $this->loadComponent('Auth', [
        'authenticate' => [
            'RememberMe.Cookie' => [
                'userModel' => 'Users',
                'fields' => ['username' => 'email'],
                'inputKey' => 'remember_me',
            ],
            // ... other authenticater config
        ],
        // ... other auth component config
    ]);
    // ... snip
}

```

### RememberMe.CookieAuthenticate options

#### `inputKey`

When this key is input by form authentication, it issues a login cookie.

default: `'remember_me'`

```
    'RememberMe.Cookie' => [
        'inputKey' => 'remember_me',
    ],
```


#### `always`

When this option is set to true, a login cookie is always issued after authentication identified.

default: `false`

```
    'RememberMe.Cookie' => [
        'always' => true,
    ],
```

#### `dropExpiredToken`

When this option is set to true, drop expired tokens after authentication identified.

default: `true`

```
    'RememberMe.Cookie' => [
        'dropExpiredToken' => false,
    ],
```


#### `cookie`

Write option for login cookie.

- name: cookie name (default: `'rememberMe'`)
- expires: cookie expiration (default: `'+30 days'`)
- secure: secure flag (default: `true`)
- httpOnly: http only flag (default: `true`)

```
    'RememberMe.Cookie' => [
        'cookie' => [
            'name' => 'rememberMe',
            'expires' => '+30 days',
            'secure' => true,
            'httpOnly' => true,
        ],
    ],
```


#### `tokenStorageModel`

A model used for storing login cookie tokens.

default: `'RememberMe.RememberMeTokens'`

```
    'RememberMe.Cookie' => [
        'tokenStorageModel' => 'YourTokensModel',
    ],
```


more configuration options see: https://book.cakephp.org/3.0/en/controllers/components/authentication.html#configuring-authentication-handlers
