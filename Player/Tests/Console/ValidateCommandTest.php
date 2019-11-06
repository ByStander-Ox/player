<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Console;

use Blackfire\Player\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ValidateCommandTest extends TestCase
{
    public function providePlayerTests()
    {
        $dirs = Finder::create()
            ->in(__DIR__.'/../fixtures-validate')
            ->directories();

        foreach ($dirs as $dir) {
            foreach (['output.txt', 'scenario.bkf'] as $file) {
                $file = sprintf('%s/%s', $dir->getPathname(), $file);
                if (!file_exists($file)) {
                    throw new \Exception(sprintf('The fixture file "%s" does not exist.', $file));
                }
            }

            $jsonFile = sprintf('%s/output-json.txt', $dir->getPathname());

            yield $dir->getBasename() => [
                sprintf('%s/scenario.bkf', $dir->getPathname()),
                file_get_contents(sprintf('%s/output.txt', $dir->getPathname())),
                file_exists($jsonFile) ? file_get_contents($jsonFile) : null,
            ];
        }
    }

    /** @dataProvider providePlayerTests */
    public function testValidate($file, $expectedOutput, $expectedJsonOutput)
    {
        $application = new Application();
        $tester = new CommandTester($application->get('validate'));
        $tester->execute([
            'file' => $file,
        ]);

        $output = $tester->getDisplay();
        $output = implode("\n", array_map('rtrim', explode("\n", $output)));

        $this->assertStringMatchesFormat($expectedOutput, $output);

        // For --json and --full-report, the output is composed of STDOUT + STDERR.
        // That's because the CommandTester use a StreamOutput instead of a ConsoleOutput.

        if ($expectedJsonOutput) {
            $tester->execute([
                'file' => $file,
                '--json' => true,
            ]);

            $this->assertStringMatchesFormat($expectedJsonOutput, $tester->getDisplay());
        }
    }

    public function testErrorInRealWorld()
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'run', '../Player/Tests/fixtures-validate/scenario.json', '--json'], __DIR__.'/../../../bin');
        $process->run();

        $expectedOutput = '{
    "message": "Cannot load file \"../Player/Tests/fixtures-validate/scenario.json\" because it does not have the right extension. Expected \"bkf\", got \"json\".",
    "success": false,
    "errors": [],
    "input": {
        "path": "../Player/Tests/fixtures-validate/scenario.json",
        "content": "{\n  \"message\": \"I\'m not a validate scenario file!\"\n}\n"
    }
}
';

        $expectedErrorOutput = <<<EOD
  [ERROR]                                                                      
  Cannot load file "../Player/Tests/fixtures-validate/scenario.json" because   
  it does not have the right extension. Expected "bkf", got "json".            
                                                                               
  Player documentation at https://blackfire.io/player                         
EOD;

        $this->assertSame($expectedOutput, $process->getOutput());
        $this->assertStringContainsString($expectedErrorOutput, $process->getErrorOutput());
    }
}
