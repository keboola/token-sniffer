<?php

declare(strict_types=1);

namespace Keboola\TokenSniffer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ApplicationTest extends TestCase
{
    /**
     * @dataProvider provideParamsAndOutput
     */
    public function testCall(array $parameters, int $expectedCode, array $expectedOutput): void
    {
        $cmd = array_merge([__DIR__ . '/../../bin/token-sniffer'], $parameters);
        $process = new Process($cmd, __DIR__);
        $process->run();
        $processOutput = explode("\n", trim($process->getOutput()));
        rsort($expectedOutput);
        rsort($processOutput);
        $this->assertEquals($expectedOutput, $processOutput);
        $this->assertSame($expectedCode, $process->getExitCode());
    }

    public function provideParamsAndOutput(): array
    {
        return [
            'missing param' => [
                [],
                2,
                ['Please supply the path to check as parameter'],
            ],
            'nonexistent path' => [
                ['./does-not-exist'],
                2,
                ['Path "./does-not-exist" parsed as "" does not exist'],
            ],
            'manage-token-present' => [
                ['../fixtures/manage-token-present'],
                2,
                // phpcs:disable Generic.Files.LineLength.MaxExceeded
                [
                    'other-file-with-manage-token.txt: manage-token found on line 5: 6-6fIHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF : token',
                    'subdirectory/other-file-with-manage-token.txt: manage-token found on line 11: Quisque vehicula varius turpis at eleifend. 7-689IHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF Proin tellus ante.',
                ],
            ],
            'storage-token-present' => [
                ['../fixtures/storage-token-present'],
                2,
                // phpcs:disable Generic.Files.LineLength.MaxExceeded
                [
                    'some-file-with-storage-token.txt: storage-token found on line 7: 78-6-6fIHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF : token',
                ],
            ],
            'both-tokens-present' => [
                ['../fixtures/both-tokens-present'],
                2,
                // phpcs:disable Generic.Files.LineLength.MaxExceeded
                [
                    'some/deep/structure/other-file-with-manage-token.txt: manage-token found on line 11: Quisque vehicula varius turpis at eleifend. 7-689IHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF Proin tellus ante.',
                    'some/deep/some-file-with-storage-token.txt: storage-token found on line 7: 78-6-6fIHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF : token',
                ],
            ],
            'no-token-present' => [
                ['../fixtures/no-token-present'],
                0,
                [''],
            ],
            'exclude-some' => [
                ['--exclude=excluded-dir', '--exclude=excluded-dir2', '../fixtures/exclude-some'],
                2,
                [
                    'some/deep/structure/other-file-with-manage-token.txt: manage-token found on line 11: Quisque vehicula varius turpis at eleifend. 7-689IHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF Proin tellus ante.',
                    'some/deep/some-file-with-storage-token.txt: storage-token found on line 7: 78-6-6fIHMOtv5lVKkalpgbl77nSdjcQHi9eXTZStyNtF : token',
                ],
            ],
        ];
    }
}
