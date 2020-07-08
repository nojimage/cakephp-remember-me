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

このプラグインは、Cookieによって永続的にログインする認証ハンドラを提供します。 暗号化されたユーザー名/パスワードをCookieに設定する代わりに、トークンを発行する方法を使用します。

This library inspired by Barry Jaspan's article "[Improved Persistent Login Cookie Best Practice](http://jaspan.com/improved_persistent_login_cookie_best_practice)", and Gabriel Birke's libray "https://github.com/gbirke/rememberme".

## インストール

[composer](http://getcomposer.org) を使用してインストールできます。

以下のようにして、Composer経由でプラグインをCakePHPアプリケーションへ追加します:

```
composer require nojimage/cakephp-remember-me
```

アプリケーションの `bootstrap.php` ファイルへ、次の行を追加します:

```
Plugin::load('RememberMe');
```

マイグレーションを実行し、データベースへ必要なテーブルを作成します:

```
bin/cake migrations migrate -p RememberMe
```

## 使用方法

`AppController` での AuthComponent を以下のようにして呼び出します:

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
            // ... 他の認証ハンドラーの設定
        ],
        // ... その他のAuthコンポーネント設定
    ]);
    // ... snip
}

```

### RememberMe.CookieAuthenticate のオプション

#### `inputKey`

フォーム認証でこのキーに値が入力されると、ログインCookieが発行されます。フォーム側ではこのキーでチェックボックスなどを追加してください。

default: `'remember_me'`

```
    'RememberMe.Cookie' => [
        'inputKey' => 'remember_me',
    ],
```


#### `always`

このオプションをtrueに設定すると、ログインCookieは認証が識別された後、常に発行されます。

default: `false`

```
    'RememberMe.Cookie' => [
        'always' => true,
    ],
```

#### `dropExpiredToken`

このオプションをtrueに設定すると、認証が識別された後に有効期限が切れたトークンを削除します。

default: `true`

```
    'RememberMe.Cookie' => [
        'dropExpiredToken' => false,
    ],
```


#### `cookie`

ログインCookieの書き込みオプション。

- name: cookie名 (default: `'rememberMe'`)
- expires: cookieの有効期限 (default: `'+30 days'`)
- secure: secure フラグ (default: `true`)
- httpOnly: http only フラグ (default: `true`)

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

ログインCookieトークンを格納するために使用されるモデル。

default: `'RememberMe.RememberMeTokens'`

```
    'RememberMe.Cookie' => [
        'tokenStorageModel' => 'YourTokensModel',
    ],
```


その他の設定オプションについてはこちらを確認してください: https://book.cakephp.org/3.0/ja/controllers/components/authentication.html#configuring-authentication-handlers

## Authenticationプラグインでの使用方法

[cakephp/authentication](https://github.com/cakephp/authentication) を使用しているのであれば、
`RememberMeTokenIdentifier` と `CookeAuthenticator` を使用してください。

`Application` の `getAuthenticationService` フックで RememberMeプラグインの Identifier と Authenticator を呼び出す例です:

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
        // ... 他の identifier や authenticator をセットアップ

        // setup RememberMe
        $service->loadIdentifier('RememberMe.RememberMeToken', compact('fields'));
        $service->loadAuthenticator('RememberMe.Cookie', [
            'fields' => $fields,
            'loginUrl' => '/users/login',
        ]);
    }
}
```

`getAuthenticationService` の説明は次のドキュメントを参考にしてください: [Quick Start - CakePHP Authentication 1.x](https://book.cakephp.org/authentication/1/en/index.html)

### RememberMe.RememberMeTokenIdentifier のオプション

#### `fields`

認証情報の参照に用いるフィールド名です。

default: `['username' => 'username']`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'fields' => [
            'username' => 'email',
        ],
    ]);
```

#### `resolver`

認証情報のリゾルバークラスとその設定を指定します。 自作のリゾルバーを指定する場合は、
 `Authentication\Identifier\Resolver\OrmResolver` を拡張したクラスを指定してください。

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

ログインクッキーのトークンを探すモデル（テーブル）クラスを指定します。

default: `'RememberMe.RememberMeTokens'`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'tokenStorageModel' => 'YourTokensModel',
    ]);
```

#### `userTokenFieldName`

認証情報にトークン情報を追加するときのプロパティ名です。

default: `'remember_me_token'`

```
    $service->loadIdentifier('RememberMe.RememberMeToken', [
        'userTokenFieldName' => 'cookie_token',
    ]);
```

### RememberMe.CookeAuthenticator のオプション

#### `loginUrl`

ログインURLは、文字列または配列のURLです。 デフォルトでは、nullがセットされ全てのページでチェックされます。

default: `null`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `urlChecker`

URLチェッカーのクラス名、またはオブジェクトを指定します。

default: `'DefaultUrlChecker'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `rememberMeField`

フォーム認証でこのキーに値が入力されると、ログインCookieが発行されます。フォーム側ではこのキーでチェックボックスなどを追加してください。

default: `'remember_me'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'rememberMeField' => 'remember_me',
    ]);
```

#### `fields`

POSTデータの指定フィールドを、`username` にマップします。

default: `['username' => 'username']`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'fields' => [
            'username' => 'email',
        ],
    ]);
```

#### `cookie`

ログインCookieの書き込みオプション。

- name: cookie名 (default: `'rememberMe'`)
- expires: cookieの有効期限 (default: `'+30 days'`)
- path: パス (default: `'/'`)
- domain: ドメイン, (default: `''`)
- secure: secure フラグ (default: `true`)
- httpOnly: http only フラグ (default: `true`)

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

ログインCookieトークンを格納するために使用されるモデル。

default: `'RememberMe.RememberMeTokens'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'tokenStorageModel' => 'YourTokensModel',
    ]);
```

#### `always`

このオプションをtrueに設定すると、ログインCookieは認証が識別された後、常に発行されます。

default: `false`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'always' => true,
    ]);
```

#### `dropExpiredToken`

このオプションをtrueに設定すると、認証が識別された後に有効期限が切れたトークンを削除します。

default: `true`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'dropExpiredToken' => false,
    ]);
```
