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

このプラグインは、Cookieによって永続的にログインする認証ハンドラを提供します。 暗号化されたユーザー名/パスワードをCookieに設定する代わりに、トークンを発行する方法を使用します。

This library is inspired by Barry Jaspan's article "[Improved Persistent Login Cookie Best Practice](http://jaspan.com/improved_persistent_login_cookie_best_practice)", and Gabriel Birke's library "https://github.com/gbirke/rememberme".

## インストール

[composer](http://getcomposer.org) を使用してインストールできます。

以下のようにして、Composer経由でプラグインをCakePHPアプリケーションへ追加します:

```shell
php composer.phar require nojimage/cakephp-remember-me:^5.0
```

アプリケーションの `src/Application.php` ファイルへ、次の行を追加します:

```php
$this->addPlugin('RememberMe');
```

もしくは、次のコンソールコマンドを実行します

```shell
bin/cake plugin load RememberMe
```

マイグレーションを実行し、データベースへ必要なテーブルを作成します:

```shell
bin/cake migrations migrate -p RememberMe
```

## Authenticationプラグインでの使用方法

[cakephp/authentication](https://github.com/cakephp/authentication) を使用しているのであれば、
`RememberMeTokenIdentifier` と `CookieAuthenticator` を使用してください。
cakephp/authentication 3.x（3.3.4 以降）と 4.x の両方に対応しています。

`Application` の `getAuthenticationService` フックで RememberMeプラグインの Identifier と Authenticator を呼び出す例です:

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
        // ... 他の authenticator をセットアップ

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

cakephp/authentication 3.x では、`identifier` を配列で指定するときに Identifier 名をキーにします:

```php
        $service->loadAuthenticator('RememberMe.Cookie', [
            'identifier' => [
                'RememberMe.RememberMeToken' => ['fields' => $fields],
            ],
            'fields' => $fields,
            'loginUrl' => '/users/login',
        ]);
```

オプションを指定しない `'identifier' => 'RememberMe.RememberMeToken'` は、どちらの版でも使えます。
3.x では `$service->loadIdentifier()` も引き続き使えますが、3.3.0 から非推奨で、4.x では削除されています。

4.x で `identifier` を省略すると、Authenticator の `fields` と `tokenStorageModel` を引き継いだ
`RememberMeTokenIdentifier` が作られます。このリゾルバーは `Users` モデルを使うため、
ユーザーモデルが異なる場合は `identifier` を指定してください。

`getAuthenticationService` の説明は次のドキュメントを参考にしてください: [Quick Start - CakePHP Authentication 4.x](https://book.cakephp.org/authentication/4/)

### RememberMe.RememberMeTokenIdentifier のオプション

以下の例は 4.x の書き方です。
3.x では `'identifier' => ['RememberMe.RememberMeToken' => [...]]` の中にオプションを書いてください。

#### `fields`

認証情報の参照に用いるフィールド名です。

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

認証情報のリゾルバークラスとその設定を指定します。 自作のリゾルバーを指定する場合は、
`Authentication\Identifier\Resolver\OrmResolver` を拡張したクラスを指定してください。

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

ログインクッキーのトークンを探すモデル（テーブル）クラスを指定します。

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

認証情報にトークン情報を追加するときのプロパティ名です。

default: `'remember_me_token'`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'identifier' => [
            'className' => 'RememberMe.RememberMeToken',
            'userTokenFieldName' => 'cookie_token',
        ],
    ]);
```

### RememberMe.CookieAuthenticator のオプション

#### `identifier`

ログインCookieのトークンを検証する Identifier です。上記を参照してください。

default: `null`（`RememberMeTokenIdentifier` が作られます）

#### `loginUrl`

ログインURLです。 デフォルトでは、nullがセットされ全てのページでチェックされます。

default: `null`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'loginUrl' => '/users/login',
    ]);
```

#### `urlChecker`

URLチェッカーのクラス名、またはオブジェクトを指定します。

default: `'Authentication.Default'`

`Authentication.Default` の動作は cakephp/authentication の版によって異なります:

- 3.x: URLを文字列として比較します。URLの配列と `useRegex` オプションを使えます。
- 4.x: `Router::url()` を通してURLを比較するため、ルート配列も使えます。
  複数のURLには `'Authentication.Multi'` を、正規表現には `'Authentication.String'` を指定してください。

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

このオプションをtrueに設定すると、認証が成功した後、常にログインCookieが発行されます。

default: `false`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'always' => true,
    ]);
```

#### `dropExpiredToken`

このオプションをtrueに設定すると、認証が成功した後に有効期限が切れたトークンを削除します。

default: `true`

```
    $service->loadAuthenticator('RememberMe.Cookie', [
        'dropExpiredToken' => false,
    ]);
```
