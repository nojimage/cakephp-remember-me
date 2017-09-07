# RememberMe authentication adapter plugin for CakePHP 3

This library inspired by Barry Jaspan's article "[Improved Persistent Login Cookie Best Practice](http://jaspan.com/improved_persistent_login_cookie_best_practice)", and Gabriel Birke's libray "https://github.com/gbirke/rememberme"

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
        // ... other config
        'authenticate' => [
            // ... other authenticater config
            'RememberMe.Cookie' => [
                'userModel' => 'Users',
                'fields' => ['username' => 'email'],
                'inputKey' => 'remember_me',
            ],
        ],
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


#### `cookie`

Write option for login cookie.

- name: cookie name
- expires: cookie expiration
- secure: secure flag
- httpOnly: http only flag

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
