# RememberMe authentication adapter plugin for CakePHP

<p align="center">
    <a href="LICENSE.txt" target="_blank">
        <img alt="Software License" src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square">
    </a>
    <a href="https://github.com/nojimage/cakephp-remember-me/actions" target="_blank">
        <img alt="Build Status" src="https://img.shields.io/github/actions/workflow/status/nojimage/cakephp-remember-me/ci.yml?style=flat-square">
    </a>
    <a href="https://codecov.io/gh/nojimage/cakephp-remember-me" target="_blank">
        <img alt="Codecov" src="https://img.shields.io/codecov/c/github/nojimage/cakephp-remember-me.svg?style=flat-square">
    </a>
    <a href="https://packagist.org/packages/nojimage/cakephp-remember-me" target="_blank">
        <img alt="Latest Stable Version" src="https://img.shields.io/packagist/v/nojimage/cakephp-remember-me.svg?style=flat-square">
    </a>
</p>

This plugin provides an authentication handler that enables permanent login via cookie. This plugin uses a method of issuing a token instead of setting an encrypted username / password in a cookie.

This library is inspired by Barry Jaspan's article "[Improved Persistent Login Cookie Best Practice](http://jaspan.com/improved_persistent_login_cookie_best_practice)", and Gabriel Birke's library "https://github.com/gbirke/rememberme".

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```shell
php composer.phar require nojimage/cakephp-remember-me:^5.0
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
use `RememberMeTokenIdentifier` and `CookieAuthenticator`.
Both cakephp/authentication 3.x (3.3.4 or later) and 4.x are supported.

Example of loading RememberMe's Identifier and Authenticator into the `getAuthenticationService` hook within `Application`:

```php
// in your src/Application.php
class Application extends ...
{
    public function getAuthenticationService(...): void
    {
        $service = new AuthenticationService();
        $fields = [
            'username' => 'email',
            'password' => 'password'
        ];
        // ... setup other authenticators

        // setup RememberMe
        $service->loadAuthenticator('RememberMe.Cookie', [
            'identifier' => [
                'className' => 'RememberMe.RememberMeToken',
                'fields' => $fields,
            ],
            'fields' => $fields,
            'loginUrl' => '/users/login',
        ]);
    }
}
```

With cakephp/authentication 3.x, an `identifier` array is keyed by the identifier name:

```php
        $service->loadAuthenticator('RememberMe.Cookie', [
            'identifier' => [
                'RememberMe.RememberMeToken' => ['fields' => $fields],
            ],
            'fields' => $fields,
            'loginUrl' => '/users/login',
        ]);
```

`'identifier' => 'RememberMe.RememberMeToken'` (without options) works on both versions.
`$service->loadIdentifier()` still works on 3.x, but it is deprecated since 3.3.0 and removed in 4.x.

With 4.x, if `identifier` is omitted, a `RememberMeTokenIdentifier` is created
with the authenticator's `fields` and `tokenStorageModel`. Its resolver uses the `Users` model,
so set `identifier` when your user model is different.

more document for `getAuthenticationService`, see: [Quick Start - CakePHP Authentication 4.x](https://book.cakephp.org/authentication/4/)

### RememberMe.RememberMeTokenIdentifier options

The examples below use the 4.x format.
With 3.x, put the options in `'identifier' => ['RememberMe.RememberMeToken' => [...]]`.

#### `fields`

The fields for the lookup.

default: `['username' => 'username']`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'identifier' => [
            'className' => 'RememberMe.RememberMeToken',
            'fields' => [
                'username' => 'email',
            ],
        ],
    ]);
```

#### `resolver`

The identity resolver. If you change your Resolver,
it must extend `Authentication\Identifier\Resolver\OrmResolver`.

default: `'Authentication.Orm'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'identifier' => [
            'className' => 'RememberMe.RememberMeToken',
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'Administrators',
            ],
        ],
    ]);
```

#### `tokenStorageModel`

A model used for finding login cookie tokens.

default: `'RememberMe.RememberMeTokens'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'identifier' => [
            'className' => 'RememberMe.RememberMeToken',
            'tokenStorageModel' => 'YourTokensModel',
        ],
    ]);
```

#### `userTokenFieldName`

A property name when adding token data to identity.

default: `'remember_me_token'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'identifier' => [
            'className' => 'RememberMe.RememberMeToken',
            'userTokenFieldName' => 'cookie_token',
        ],
    ]);
```

### RememberMe.CookeAuthenticator options

#### `identifier`

The identifier used to verify login cookie tokens. See above.

default: `null` (a `RememberMeTokenIdentifier` is created)

#### `loginUrl`

The login URL. Default is null and all pages will be checked.

default: `null`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `urlChecker`

The URL checker class or object.

default: `'Authentication.Default'`

The behavior of `Authentication.Default` depends on the cakephp/authentication version:

- 3.x: compares the URL as a string. It accepts an array of URLs and the `useRegex` option.
- 4.x: compares the URL through `Router::url()`, so a route array is also accepted.
  Use `'Authentication.Multi'` for multiple URLs, and `'Authentication.String'` for regular expressions.

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'urlChecker' => 'Authentication.Multi',
        'loginUrl' => [
            '/en/users/login',
            '/ja/users/login',
        ],
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

When this option is set to true, a login cookie is always issued after successful authentication.

default: `false`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'always' => true,
    ]);
```

#### `dropExpiredToken`

When this option is set to true, expired tokens are dropped after successful authentication.

default: `true`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'dropExpiredToken' => false,
    ]);
```
