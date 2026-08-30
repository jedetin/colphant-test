<?php

interface Logger
{
    public function info(
        string $generator,
        string $message
    ): void;

    public function success(
        string $generator,
        string $message
    ): void;

    public function warning(
        string $generator,
        string $message
    ): void;

    public function error(
        string $generator,
        string $message
    ): void;

    public function section(
        string $generator
    ): void;
}
