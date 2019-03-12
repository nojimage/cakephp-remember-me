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

#### `setAuthUser`

このオプションをtrueに設定すると、Cookieでの認証時にAuthComponentでユーザー情報をセッションにセットします。

default: `true`

```
    'RememberMe.Cookie' => [
        'setAuthUser' => false,
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
