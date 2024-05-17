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
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)', aliases: ['clone'])]
function build(): void
{
    infra\build();
    infra\start();
    cache_clear();
    install();
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the Aropixel adminBundle suite (admin, page , blog, contact)', aliases: ['init'])]
function init(): void
{
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git AdminBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git PageBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git BlogBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git ContactBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('bin/console doctrine:schema:update --force ', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('git config --global --add safe.directory /home/kby/www/symfony-docker-app/aropixel/AdminBundle', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('git config --global --add safe.directory /home/kby/www/symfony-docker-app/aropixel/PageBundle', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('git config --global --add safe.directory /home/kby/www/symfony-docker-app/aropixel/BlogBundle', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('git config --global --add safe.directory /home/kby/www/symfony-docker-app/aropixel/ContactBundle', workDir: '/var/www/symfony-docker-app/app');

    docker_compose_run('npm run dev', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('bin/console assets:install --relative', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('bin/console aropixel:admin:setup', workDir: '/var/www/symfony-docker-app/app');
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    docker_compose_run('composer install -n --prefer-dist --optimize-autoloader', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('yarn', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('yarn encore dev', workDir: '/var/www/symfony-docker-app/app');

}

#[AsTask(description: 'Clear the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    // docker_compose_run('rm -rf var/cache/ && bin/console cache:warmup');
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    docker_compose_run('./bin/console doctrine:database:create --if-not-exists', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration', workDir: '/var/www/symfony-docker-app/app');
}


#[AsTask(description: 'Compile the assets', namespace: 'infra:assets', name: 'compile', aliases: ['compile-assets'])]
function compile_assets(): void
{
    docker_compose_run('npm run dev', workDir: '/var/www/symfony-docker-app/app');
}

