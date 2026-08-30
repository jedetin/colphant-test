<?php
// include 'Logger.php';

abstract class CPGenerator
{
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    abstract public function name(): string;

    abstract public function generate(): CPGeneratorResult;

    protected function info(string $message): void
    {
        $this->logger->info($this->name(), $message);
    }

    protected function success(string $message): void
    {
        $this->logger->success($this->name(), $message);
    }

    protected function warning(string $message): void
    {
        $this->logger->warning($this->name(), $message);
    }

    protected function error(string $message): void
    {
        $this->logger->error($this->name(), $message);
    }

    protected function section(): void
    {
        $this->logger->section($this->name());
    }

    protected function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(
                "Unable to write file: {$path}"
            );
        }

        $this->logger->success(
            $this->name(),
            "Generated {$path}"
        );
    }
}
