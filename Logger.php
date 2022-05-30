<?php

class Logger {
    protected $path;

    public function __construct(string $path=__DIR__.'/LogTwitterBot.txt') {
        if (file_exists($path))
            file_put_contents($path, '');
        else
            touch($path);
        $this->path = $path;
    }

    public function print(string $str, string $origin=''): void {
        if ($str == null || $str == '')
            return;
        $baseString = '['.date("d/m/Y").' - '.date("H:i:s").'] ';
        $baseString .= $origin;
        $result = $baseString.$str.PHP_EOL;

        echo $result;
        $this->addToFile($result);
    }

    public function addToFile(string $content): void {
        if ($content == null || $content == '')
            return;
        $file = file_get_contents($this->path);
        $file .= $content;
        file_put_contents($this->path, $file);
    }
}