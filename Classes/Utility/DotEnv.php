<?php

namespace Univie\UniviePure\Utility;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class DotEnv
{
    protected $path;
    public $variables;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \Exception("File not found: {$path}");
        }
        $this->path = $path;
    }

    public function load(): void
    {
        if (!is_readable($this->path)) {
            throw new \Exception("File not readable: {$this->path}");
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue; // Skip invalid lines
            }

            $name = trim($parts[0]);
            $value = trim($parts[1], "\x00..\x1F\"");

            if (empty($name)) {
                continue;
            }

            $this->variables[$name] = $value;

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
