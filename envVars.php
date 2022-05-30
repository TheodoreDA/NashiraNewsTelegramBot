<?php

class EnvVars {
    protected $path;

    public function __construct(string $path) {
        if(!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('%s does not exist', $path));
        }
        $this->path = $path;
    }

    public function set_env_var(string $varName, string $varValue): void {
        if (!is_readable($this->path)) {
            throw new \RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        $newLines = array();
        for ($i = 0; $i < count($lines); $i++) {
            $lines[$i] = trim($lines[$i]);
            list($lines[$i]) = explode('#', $lines[$i], 1);
            if (strlen($lines[$i]) == 0)
                continue;
            list($name, $value) = explode('=', $lines[$i], 2);
            $name = trim($name);
            $value = trim($value);
            if ($name == $varName) {
                $value = $varValue;
                $varName = null;
            }
            array_push($newLines, $name.'='.$value);
        }
        if ($varName != null)
            array_push($newLines, $varName.'='.$varValue);
        file_put_contents($this->path, implode(PHP_EOL, $newLines));
    }

    public function get_env_var(string $varName): string | null {
        if (!is_readable($this->path)) {
            throw new \RuntimeException(sprintf('%s file is not readable', $this->path));
        }
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name == $varName)
                return $value;
        }
        return null;
    }
}