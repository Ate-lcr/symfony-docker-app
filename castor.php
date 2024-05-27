<?php

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;

import(__DIR__ . '/.castor');

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
        'symfony_version' => '6.4',
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)', aliases: ['stack:build'])]
function build_stack(): void
{
    infra\build();
    infra\up();
    install_symfony();
    clone_bundles();
    cache_clear();
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Clone the Aropixel adminBundle suite (admin, page , blog, contact)', aliases: ['stack:clone'])]
function clone_bundles(): void
{
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git AdminBundle', workDir: '/var/www/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git PageBundle', workDir: '/var/www/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git BlogBundle', workDir: '/var/www/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git ContactBundle', workDir: '/var/www/aropixel');
    docker_compose_run('bin/console doctrine:schema:update --force ', workDir: '/var/www/app');
    docker_compose_run('git config --global --add safe.directory .', workDir: '/var/www/aropixel/AdminBundle');
    docker_compose_run('git config --global --add safe.directory .', workDir: '/var/www/aropixel/PageBundle');
    docker_compose_run('git config --global --add safe.directory .', workDir: '/var/www/aropixel/BlogBundle');
    docker_compose_run('git config --global --add safe.directory .', workDir: '/var/www/aropixel/ContactBundle');

    docker_compose_run('npm run dev', workDir: '/var/www/app');
    docker_compose_run('bin/console assets:install --relative', workDir: '/var/www/app');
    docker_compose_run('bin/console aropixel:admin:setup', workDir: '/var/www/app');
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app', aliases: ['install'])]
function install_symfony(): void
{
    docker_compose_run('composer create-project "symfony/skeleton ^"'.variable('symfony_version').' app --prefer-dist --no-progress --no-interaction --no-install', workDir: '/var/www');
    docker_compose_run('composer install -n --prefer-dist --optimize-autoloader', workDir: '/var/www/app');
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

