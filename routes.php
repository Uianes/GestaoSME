<?php
function base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    return $dir === '' ? '' : $dir;
}
function asset(string $path): string
{
    return base_url() . '/' . ltrim($path, '/');
}
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}