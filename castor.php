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

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    infra\build();
    infra\up();
    cache_clear();
    install();
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
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

