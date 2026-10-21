<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class EnvironmentConfigurationTest extends TestCase
{
    private const string BASE_PATH = __DIR__.'/../..';

    /** Every variable read by the configuration is listed in the example file. */
    public function test_example_file_lists_every_configuration_variable(): void
    {
        preg_match_all('/^#?\s*([A-Z][A-Z0-9_]*)=/m', $this->read('.env.example'), $documented);

        $undocumented = array_diff($this->variablesReadIn('config'), $documented[1]);

        $this->assertSame([], array_values($undocumented));
    }

    /** Environment variables are read only from the configuration files. */
    public function test_application_code_never_reads_the_environment(): void
    {
        $this->assertSame([], $this->variablesReadIn('app'));
        $this->assertSame([], $this->variablesReadIn('src'));
    }

    /** The real environment file stays out of version control. */
    public function test_environment_file_is_ignored_by_git(): void
    {
        $this->assertContains('.env', explode("\n", $this->read('.gitignore')));
    }

    /**
     * Names of the variables requested through env() under a directory.
     *
     * @return list<string>
     */
    private function variablesReadIn(string $directory): array
    {
        $variables = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::BASE_PATH.'/'.$directory));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                preg_match_all('/\benv\(\s*[\'"]([A-Z0-9_]+)[\'"]/', (string) file_get_contents($file->getPathname()), $matches);
                $variables = [...$variables, ...$matches[1]];
            }
        }

        return array_values(array_unique($variables));
    }

    /** Read a file relative to the application root. */
    private function read(string $path): string
    {
        return (string) file_get_contents(self::BASE_PATH.'/'.$path);
    }
}
