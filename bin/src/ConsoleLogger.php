<?php
include 'Logger.php';
final class ConsoleLogger implements Logger
{
    public function info(
        string $generator,
        string $message
    ): void {
        $this->write($generator, $message);
    }

    public function success(
        string $generator,
        string $message
    ): void {
        $this->write(
            $generator,
            "✓ {$message}"
        );
    }

    public function warning(
        string $generator,
        string $message
    ): void {
        $this->write(
            $generator,
            "WARNING: {$message}"
        );
    }

    public function error(
        string $generator,
        string $message
    ): void {
        $this->write(
            $generator,
            "ERROR: {$message}"
        );
    }

    public function section(
        string $generator
    ): void {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo " {$generator}" . PHP_EOL;
        echo "========================================" . PHP_EOL;
    }

    private function write(
        string $generator,
        string $message
    ): void {
        echo sprintf(
            "<br/>[%s] %-8s %s%s",
            date('H:i:s'),
            strtoupper($generator),
            $message,
            PHP_EOL
        );
    }
}
