<?php

declare(strict_types=1);

namespace Keboola\TokenSniffer;

use Exception;
use Symfony\Component\Process\Process;

class Application
{
    private const PATTERNS = [
        'storage-token' => '[0-9]+-[0-9]+-[0-9A-Za-z]{40}+',
        'manage-token' => '[0-9]+-[0-9A-Za-z]{40}+',
    ];

    /** @var string */
    private $path;

    /** @var array */
    private $excludedDirs;

    public function __construct()
    {
        $input = $this->parseInput();
        $path = $input[0];
        $realpath = realpath($path);
        if (!$realpath) {
            throw new Exception(sprintf('Path "%s" parsed as "%s" does not exist', $path, $realpath));
        }
        $this->path = $realpath . '/';
        $this->excludedDirs = $input['exclude'] ?? [];
        $this->excludedDirs[] = '.idea';
        $this->excludedDirs[] = '.git';
        $this->excludedDirs = array_unique($this->excludedDirs);
    }

    public function run(): void
    {
        $patterns = array_map(function (string $pattern): string {
            return '(' . $pattern . ')';
        }, self::PATTERNS);
        $regexp = implode('|', $patterns);

        $cmd = [
            'grep',
            '-rP',
            '--line-number',
        ];
        if (count($this->excludedDirs)) {
            $excludedDirsParams = array_map(function ($dir) {
                return '--exclude-dir=' . $dir;
            }, $this->excludedDirs);
            $cmd = array_merge($cmd, $excludedDirsParams);
        }
        array_push($cmd, $regexp, $this->path);

        $process = new Process($cmd);
        $process->run();

        if ($process->getExitCode() !== 0) {
            // patterns not found
            echo "\n\n[OK] No errors\n\n";
            exit(0);
        }
        $output = $process->getOutput();
        $matches = $this->parseOutput($output);
        throw new Exception($this->buildViolations($matches));
    }

    private function parseOutput(string $output): array
    {
        $matches = explode("\n", trim($output));
        array_walk($matches, 'trim');
        array_walk($matches, function (string &$match): void {
            $match = explode(':', $match, 3);
        });
        return $matches;
    }

    private function buildViolations(array $matches): string
    {
        $errorMessages = [];
        foreach ($matches as $match) {
            foreach (self::PATTERNS as $patternName => $pattern) {
                if (preg_match('~' . $pattern . '~', $match[2])) {
                    $violationFile = str_replace($this->path, '', $match[0]);
                    $errorMessages[] = sprintf(
                        '%s: %s found on line %s: %s',
                        $violationFile,
                        $patternName,
                        $match[1],
                        $match[2]
                    );
                    break;
                }
            }
        }
        return implode("\n", $errorMessages) . "\n";
    }

    private function parseInput(): array
    {
        global $argv;
        $optind = null;
        $opts = getopt('', ['exclude::'], $optind);
        $pos_args = array_slice($argv, $optind);
        if (!array_key_exists(0, $pos_args)) {
            throw new Exception('Please supply the path to check as parameter' . "\n");
        }
        if (array_key_exists('exclude', $opts) && is_string($opts['exclude'])) {
            $opts['exclude'] = [$opts['exclude']];
        }
        return array_merge($opts, $pos_args);
    }
}
