<?php
/** @noinspection MkdirRaceConditionInspection */
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Authentication\Plugin as AuthenticationPlugin;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\Fixture\SchemaLoader;
use Migrations\TestSuite\Migrator;

/**
 * Test suite bootstrap for CakePHP Plugin.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);

    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);

$here = __DIR__;

// Ensure default test connection is defined
if (!getenv('DB_URL')) {
    putenv('DB_URL=sqlite:///' . sys_get_temp_dir() . 'test.sqlite');
}

chdir($root);
require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';
require CAKE . '/functions.php';

date_default_timezone_set('UTC');
$_SERVER['PHP_SELF'] = '/';

Plugin::getCollection()->add(new AuthenticationPlugin());

// setup migration
$schemaLoader = new SchemaLoader();
$schemaLoader->loadInternalFile($here . '/schema.php');

$migrator = new Migrator();
$migrator->run([
    'plugin' => 'RememberMe',
    'skip' => ['auth_users', 'users'],
]);

Cache::clearAll();

error_reporting(E_ALL);

Configure::write('App.namespace', 'TestApp');
