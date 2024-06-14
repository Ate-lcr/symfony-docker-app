<?php

use Castor\Attribute\AsTask;

use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;
use function docker\about;
use function docker\build;
use function docker\docker_compose_run;
use function docker\generate_certificates;
use function docker\up;
use function init\symfony;

guard_min_version('0.15.0');

import(__DIR__ . '/.castor');

const BUNDLES = [
    'AdminBundle' => 'admin-bundle',
    'BlogBundle' => 'blog-bundle',
    'PageBundle' => 'page-bundle',
    'MenuBundle' => 'menu-bundle',
    'ContactBundle' => 'contact-bundle',
];

/**
 * @return array<string, mixed>
 */
function create_default_variables(): array
{
    $projectName = 'adminbundle';

    return [
        'project_name' => $projectName,
        'root_domain' => "adminbundle.local",
        'php_version' => '8.2',
        'symfony_version' => '^6.4',
    ];
}


#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    // workers_stop();
    generate_certificates(force: false);
    build();
    clone_stack();
    up();
    cache_clear();
    install();
    migrate();
    // workers_start();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Install the application (composer, yarn, ...)')]
function clone_stack(): void
{
    $basePath = sprintf('%s/app', variable('root_dir'));
    if (!is_file("{$basePath}/composer.json")) {
        io()->section('Create symfony app');
        symfony();
    }

    docker_compose_run('mkdir -p aropixel', workDir: '/var/www');
    $basePath = sprintf('%s/aropixel', variable('root_dir'));

    foreach (BUNDLES as $dir => $repo) {
        if (!file_exists("{$basePath}/{$dir}")) {
            io()->section("Clone {$dir}");
            docker_compose_run("git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/{$repo}.git {$dir}", workDir: '/var/www/aropixel');
            docker_compose_run('git config --global --add safe.directory .', workDir: "/var/www/aropixel/{$dir}");
            docker_compose_run("composer config repositories.aropixel/{$repo} path ../aropixel/{$dir}", workDir: "/var/www/app");
        }
    }
}

#[AsTask(description: 'Install the application (composer, yarn, ...)')]
function install(): void
{
    docker_compose_run('composer install', workDir: '/var/www/app');
    docker_compose_run('cp -f ../infra/files/bundles.php config/', workDir: '/var/www/app');

    foreach (BUNDLES as $repo) {
        docker_compose_run("composer require aropixel/{$repo} *@dev -n", workDir: '/var/www/app');
    }

    io()->title('Installing the application');

    $basePath = sprintf('%s/app', variable('root_dir'));

    if (is_file("{$basePath}/composer.json")) {
        io()->section('Installing PHP dependencies');
        docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');
    }
    if (is_file("{$basePath}/yarn.lock")) {
        io()->section('Installing Node.js dependencies');
        docker_compose_run('yarn install --frozen-lockfile');
    } elseif (is_file("{$basePath}/package.json")) {
        io()->section('Installing Node.js dependencies');

        if (is_file("{$basePath}/package-lock.json")) {
            docker_compose_run('npm ci');
        } else {
            docker_compose_run('npm install');
        }
    }
    if (is_file("{$basePath}/importmap.php")) {
        io()->section('Installing importmap');
        docker_compose_run('bin/console importmap:install');
    }

//    docker_compose_run('bin/console aropixel:admin:setup', workDir: '/var/www/app');

    qa\install();

}

#[AsTask(description: 'Clear the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    // docker_compose_run('rm -rf var/cache/ && bin/console cache:warmup');
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    docker_compose_run('./bin/console doctrine:database:create --if-not-exists', workDir: '/var/www/app');
    docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration', workDir: '/var/www/app');
}


#[AsTask(description: 'Compile the assets', namespace: 'infra:assets', name: 'compile', aliases: ['compile-assets'])]
function compile_assets(): void
{
    docker_compose_run('npm run dev', workDir: '/var/www/app');
}

