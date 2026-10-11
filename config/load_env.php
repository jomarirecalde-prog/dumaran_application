<?php
/**
 * Load key=value pairs from .env (and optional .env.local) into $_ENV / putenv().
 */

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            $equals = strpos($line, '=');
            if ($equals === false) {
                continue;
            }

            $name = trim(substr($line, 0, $equals));
            if ($name === '') {
                continue;
            }

            $value = trim(substr($line, $equals + 1));
            $len = strlen($value);
            if (
                $len >= 2
                && (
                    ($value[0] === '"' && $value[$len - 1] === '"')
                    || ($value[0] === "'" && $value[$len - 1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

$projectRoot = dirname(__DIR__);
loadEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env');
loadEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env.local');
