<?php

declare(strict_types=1);

namespace Keboola\TokenSniffer;

use Exception;
use Symfony\Component\Process\Process;

class Application
{
    private const PARAM_EXCLUDE_FILE = 'exclude-file';
    private const PARAM_EXCLUDE = 'exclude';
    private const PATTERNS = [
        'storage-token' => '[0-9]+-[0-9]+-[0-9A-Za-z]{40}+',
        'manage-token' => '[0-9]+-[0-9A-Za-z]{40}+',
    ];

    /** @var string */
    private $path;

    /** @var array */
    private $excludedDirs;

    /** @var array */
    private $excludedFiles;

    public function __construct()
    {
        $input = $this->parseInput();
        $path = $input[0];
        $realpath = realpath($path);
        if (!$realpath) {
            throw new Exception(sprintf('Path "%s" parsed as "%s" does not exist', $path, $realpath));
        }
        $this->path = $realpath . '/';
        $this->excludedDirs = $input[self::PARAM_EXCLUDE] ?? [];
        $this->excludedDirs[] = '.idea';
        $this->excludedDirs[] = '.git';
        $this->excludedDirs = array_unique($this->excludedDirs);
        $this->excludedFiles = $input[self::PARAM_EXCLUDE_FILE] ?? [];
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
            $this->exitWithSuccess();
        }
        $output = $process->getOutput();
        $matches = $this->parseOutput($output);

        $errorMessages = $this->buildErrorMessages($matches);
        if (!count($errorMessages)) {
            $this->exitWithSuccess();
        }

        throw new Exception(implode("\n", $errorMessages) . "\n";);
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

    private function buildErrorMessages(array $matches): string
    {
        $errorMessages = [];
        foreach ($matches as $match) {
            $filepath = $match[0];
            $line = $match[1];
            $lineContents = $match[2];
            $violationFile = str_replace($this->path, '', $filepath);
            if (in_array($violationFile, $this->excludedFiles)) {
                continue;
            }

            foreach (self::PATTERNS as $patternName => $pattern) {
                if (preg_match('~' . $pattern . '~', $lineContents)) {
                    $errorMessages[] = sprintf(
                        '%s: %s found on line %s: %s',
                        $violationFile,
                        $patternName,
                        $line,
                        $lineContents
                    );
                    break;
                }
            }
        }
        return $errorMessages;
    }

    private function parseInput(): array
    {
        global $argv;
        $optind = null;
        $opts = getopt('', ['exclude::', 'exclude-file::'], $optind);
        $pos_args = array_slice($argv, $optind);
        if (!array_key_exists(0, $pos_args)) {
            throw new Exception('Please supply the path to check as parameter' . "\n");
        }
        if (array_key_exists(self::PARAM_EXCLUDE, $opts) && is_string($opts[self::PARAM_EXCLUDE])) {
            $opts[self::PARAM_EXCLUDE] = [$opts[self::PARAM_EXCLUDE]];
        }
        if (array_key_exists(self::PARAM_EXCLUDE_FILE, $opts) && is_string($opts[self::PARAM_EXCLUDE_FILE])) {
            $opts[self::PARAM_EXCLUDE_FILE] = [$opts[self::PARAM_EXCLUDE_FILE]];
        }
        return array_merge($opts, $pos_args);
    }

    private function exitWithSuccess(): void
    {
        echo "\n\n[OK] No errors\n\n";
        exit(0);
    }
}
