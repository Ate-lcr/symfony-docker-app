<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\cache;
use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\log;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Displays some help and available urls for the current project')]
function about(): void
{
    io()->section('About this project');

    io()->comment('Run <comment>castor</comment> to display all available commands.');
    io()->comment('Run <comment>castor about</comment> to display this project help.');
    io()->comment('Run <comment>castor help [command]</comment> to display Castor help.');

    io()->section('Available URLs for this project:');
    $urls = [variable('root_domain'), ...variable('extra_domains')];

    $payload = @file_get_contents(sprintf('http://%s:8080/api/http/routers', variable('root_domain')));
    if ($payload) {
        $routers = json_decode($payload, true);
        $projectName = variable('project_name');
        foreach ($routers as $router) {
            if (!preg_match("{^{$projectName}-(.*)@docker$}", $router['name'])) {
                continue;
            }
            if ("frontend-{$projectName}" === $router['service']) {
                continue;
            }
            if (!preg_match('{^Host\\(`(?P<hosts>.*)`\\)$}', $router['rule'], $matches)) {
                continue;
            }
            $hosts = explode('`) || Host(`', $matches['hosts']);
            $urls = [...$urls, ...$hosts];
        }
    }
    io()->listing(array_map(fn ($url) => "https://{$url}", $urls));
}

#[AsContext(default: true)]
function create_default_context(): Context
{
    $data = create_default_variables() + [
        'project_name' => 'adminbundle',
        'root_domain' => 'adminbundle.local',
        'extra_domains' => [],
        'project_directory' => 'app',
        'php_version' => '8.2',
        'docker_compose_files' => [
            'docker-compose.yml',
        ],
        'macos' => false,
        'power_shell' => false,
        'user_id' => posix_geteuid(),
        'root_dir' => dirname(__DIR__),
        'env' => $_SERVER['CI'] ?? false ? 'ci' : 'dev',
    ];

    if (file_exists($data['root_dir'] . '/docker/docker-compose.override.yml')) {
        $data['docker_compose_files'][] = 'docker-compose.override.yml';
    }

    // We need an empty context to run command, since the default context has
    // not been set in castor, since we ARE creating it right now
    $emptyContext = new Context();

    $data['composer_cache_dir'] = cache('composer_cache_dir', function () use ($emptyContext): string {
        $composerCacheDir = capture(['composer', 'global', 'config', 'cache-dir', '-q'], onFailure: '', context: $emptyContext);
        // If PHP is broken, the output will not be a valid path but an error message
        if (!is_dir($composerCacheDir)) {
            $composerCacheDir = sys_get_temp_dir() . '/castor/composer';
        }

        return $composerCacheDir;
    });

    if ($data['user_id'] > 256000) {
        $data['user_id'] = 1000;
    }

    if (0 === $data['user_id']) {
        log('Running as root? Fallback to fake user id.', 'warning');
        $data['user_id'] = 1000;
    }

    return new Context($data, pty: 'dev' === $data['env']);
}

function docker_exit_code(
    string $runCommand,
    Context $c = null,
    string $service = 'php',
    bool $noDeps = true,
    string $workDir = null,
    bool $withBuilder = true,
): int {
    $c = ($c ?? context())->withAllowFailure();

    $process = docker_compose_run(
        runCommand: $runCommand,
        c: $c,
        service: $service,
        noDeps: $noDeps,
        workDir: $workDir,
        withBuilder: $withBuilder,
    );

    return $process->getExitCode() ?? 0;
}

function docker_compose_run(
    string $runCommand,
    Context $c = null,
    string $service = 'php',
    bool $noDeps = true,
    string $workDir = null,
    bool $portMapping = false,
    bool $withBuilder = true,
): Process {
    $command = [
        'run',
        '--rm',
    ];

    if ($noDeps) {
        $command[] = '--no-deps';
    }

    if ($portMapping) {
        $command[] = '--service-ports';
    }

    if (null !== $workDir) {
        $command[] = '-w';
        $command[] = $workDir;
    }

    $command[] = $service;
    $command[] = '/bin/sh';
    $command[] = '-c';
    $command[] = "exec {$runCommand}";

    return docker_compose($command, c: $c, withBuilder: $withBuilder);
}

/**
 * @param array<string> $subCommand
 */
function docker_compose(array $subCommand, Context $c = null, bool $withBuilder = false): Process
{
    $c ??= context();


    $c = $c
        ->withTimeout(null)
        ->withEnvironment([
            'PROJECT_NAME' => variable('project_name'),
            'PROJECT_ROOT_DOMAIN' => variable('root_domain'),
            'USER_ID' => variable('user_id'),
            'COMPOSER_CACHE_DIR' => variable('composer_cache_dir'),
            'PHP_VERSION' => variable('php_version'),
        ])
    ;

    $command = [
        'docker',
        'compose',
        '-p', variable('project_name'),
    ];

    foreach (variable('docker_compose_files') as $file) {
        $command[] = '-f';
        $command[] = variable('root_dir') . '/' . $file;
    }
    if ($withBuilder) {
        $command[] = '-f';
        $command[] = variable('root_dir') . '/docker-compose.yml';
    }

    $command = array_merge($command, $subCommand);

    return run($command, context: $c);
}

// Mac users have a lot of problems running Yarn / Webpack on the Docker stack
// so this func allow them to run these tools on their host
function run_in_docker_or_locally_for_mac(string $command, Context $c = null): void
{
    $c ??= context();

    if (variable('macos')) {
        run($command, context: $c->withPath(variable('root_dir')));
    } else {
        docker_compose_run($command, c: $c);
    }
}

function command_arguments(array $arguments): string
{
    return implode(' ', array_map(function($k, $v){
        return "$k='$v'";
    }, array_keys($arguments), array_values($arguments)));
}
