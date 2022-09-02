# RememberMe authentication adapter plugin for CakePHP

<p align="center">
    <a href="LICENSE.txt" target="_blank">
        <img alt="Software License" src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square">
    </a>
    <a href="https://github.com/nojimage/cakephp-remember-me/actions" target="_blank">
        <img alt="Build Status" src="https://img.shields.io/github/workflow/status/nojimage/cakephp-remember-me/CakePHP%20Plugin%20CI?style=flat-square">
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

```shell
php composer.phar require nojimage/cakephp-remember-me
```

Load the plugin by adding the following statement in your project's `src/Application.php`:

```php
$this->addPlugin('RememberMe');
```

or running the console command

```shell
bin/cake plugin load RememberMe
```

Run migration:

```shell
bin/cake migrations migrate -p RememberMe
```

## Usage with Authentication plugin

If you're using [cakephp/authentication](https://github.com/cakephp/authentication),
use `RememberMeTokenIdentifier` and `CookeAuthenticator`.

Example load RememberMe's Identifier and Authenticator into the `getAuthenticationService` hook within `Application`:

```php
// in your src/Application.php
class Application extends ...
{
    public function getAuthenticationService(...)
    {
        $service = new AuthenticationService();
        $fields = [
            'username' => 'email',
            'password' => 'password'
        ];
        // ... setup other identifier and authenticator

        // setup RememberMe
        $service->loadIdentifier('RememberMe.RememberMeToken', compact('fields'));
        $service->loadAuthenticator('RememberMe.Cookie', [
            'fields' => $fields,
            'loginUrl' => '/users/login',
        ]);
    }
}
```

more document for `getAuthenticationService`, see: [Quick Start - CakePHP Authentication 2.x](https://book.cakephp.org/authentication/2/en/index.html)

### RememberMe.RememberMeTokenIdentifier options

#### `fields`

The fields for the lookup.

default: `['username' => 'username']`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'fields' => [
            'username' => 'email',
        ],
    ]);
```

#### `resolver`

The identity resolver. If change your Resolver,
 must extend `Authentication\Identifier\Resolver\OrmResolver`.

default: `'Authentication.Orm'`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'resolver' => [
            'className' => 'Authentication.Orm',
            'userModel' => 'Administrators',
        ],
    ]);
```

#### `tokenStorageModel`

A model used for find login cookie tokens.

default: `'RememberMe.RememberMeTokens'`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'tokenStorageModel' => 'YourTokensModel',
    ]);
```

#### `userTokenFieldName`

A property name when adding token data to identity.

default: `'remember_me_token'`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'userTokenFieldName' => 'cookie_token',
    ]);
```

### RememberMe.CookeAuthenticator options

#### `loginUrl`

The login URL, string or array of URLs. Default is null and all pages will be checked.

default: `null`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `urlChecker`

The URL checker class or object.

default: `'DefaultUrlChecker'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `rememberMeField`

When this key is input by form authentication, it issues a login cookie.

default: `'remember_me'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'rememberMeField' => 'remember_me',
    ]);
```

#### `fields`

Array that maps `username` to the specified POST data fields.

default: `['username' => 'username']`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'fields' => [
            'username' => 'email',
        ],
    ]);
```

#### `cookie`

Write option for login cookie.

- name: Cookie name (default: `'rememberMe'`)
- expire: Cookie expiration (default: `'+30 days'`)
- path: Path (default: `'/'`)
- domain: Domain, (default: `''`)
- secure: Secure flag (default: `true`)
- httpOnly: Http only flag (default: `true`)

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'cookie' => [
            'name' => 'rememberMe',
            'expires' => '+30 days',
            'secure' => true,
            'httpOnly' => true,
        ],
    ]);
```

#### `tokenStorageModel`

A model used for storing login cookie tokens.

default: `'RememberMe.RememberMeTokens'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'tokenStorageModel' => 'YourTokensModel',
    ]);
```

#### `always`

When this option is set to true, a login cookie is always issued after authentication identified.

default: `false`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'always' => true,
    ]);
```

#### `dropExpiredToken`

When this option is set to true, drop expired tokens after authentication identified.

default: `true`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'dropExpiredToken' => false,
    ]);
```

## [Deprecated] Usage with AuthComponent

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

more configuration options see: https://book.cakephp.org/4.0/en/controllers/components/authentication.html#configuring-authentication-handlers
