<?php

final class CPGeneratorResult
{
    public function __construct(
        private bool $success,
        private string $message = ''
    ) {}

    public static function success(
        string $message = ''
    ): self {
        return new self(true, $message);
    }

    public static function failure(
        string $message
    ): self {
        return new self(false, $message);
    }

    // public function success(): bool
    // {
    //     return $this->success;
    // }

    public function message(): string
    {
        return $this->message;
    }
}
