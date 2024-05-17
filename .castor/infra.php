<?php

namespace infra;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\context;
use function Castor\finder;
use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Builds the infrastructure', aliases: ['build'])]
function build(): void
{
    $userId = variable('user_id');
    $phpVersion = variable('php_version');

    $command = [
        'build',
        '--build-arg', "USER_ID={$userId}",
        '--build-arg', "PHP_VERSION={$phpVersion}",
    ];

    docker_compose($command, withBuilder: false);
}

#[AsTask(description: 'Builds and starts the infrastructure', aliases: ['start'])]
function start(): void
{
    try {
        docker_compose(['up', '--remove-orphans', '--detach', '--no-build']);
    } catch (ExceptionInterface $e) {
        io()->error('An error occured while starting the infrastructure.');
        io()->note('Did you forget to run "castor infra:build"?');
        io()->note('Or you forget to login to the registry?');

        throw $e;
    }
}

#[AsTask(description: 'Stops the infrastructure', aliases: ['stop'])]
function stop(): void
{
    docker_compose(['stop']);
}

#[AsTask(description: 'Opens a shell (bash) into a builder container', aliases: ['builder'])]
function builder(): void
{
    $c = context()
        ->withTimeout(null)
        ->withTty()
        ->withEnvironment($_ENV + $_SERVER)
        ->withAllowFailure()
    ;
    docker_compose_run('bash', c: $c);
}

#[AsTask(description: 'Displays infrastructure logs', aliases: ['logs'])]
function logs(): void
{
    docker_compose(['logs', '-f', '--tail', '150'], c: context()->withTty());
}

#[AsTask(description: 'Lists containers status', aliases: ['ps'])]
function ps(): void
{
    docker_compose(['ps'], withBuilder: false);
}

#[AsTask(description: 'Cleans the infrastructure (remove container, volume, networks)', aliases: ['destroy'])]
function destroy(
    #[AsOption(description: 'Force the destruction without confirmation', shortcut: 'f')]
    bool $force = false,
): void {
    if (!$force) {
        io()->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        io()->note('You can use the --force option to avoid this confirmation.');
        if (!io()->confirm('Are you sure?', false)) {
            io()->comment('Aborted.');

            return;
        }
    }

    docker_compose(['down', '--remove-orphans', '--volumes', '--rmi=local'], withBuilder: false);
}


#[AsTask(description: 'Installs the Aropixel adminBundle suite (admin, page , blog, contact)', aliases: ['init_admin'])]
function init_adminBundle(): void
{
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git AdminBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git PageBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git BlogBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('git clone -b release/v3.0.0 --single-branch https://github.com/aropixel/admin-bundle.git ContactBundle', workDir: '/var/www/symfony-docker-app/aropixel');
    docker_compose_run('bin/console doctrine:schema:update --force ', workDir: '/var/www/symfony-docker-app/app');

    docker_compose_run('npm run dev', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('bin/console assets:install --relative', workDir: '/var/www/symfony-docker-app/app');
    docker_compose_run('bin/console aropixel:admin:setup', workDir: '/var/www/symfony-docker-app/app');
}


