<?php
namespace Univie\UniviePure\Utility;

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

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, "\x00..\x1F\"");
            $this->variables[$name] = $value;

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

?>

