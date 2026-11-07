<?php
declare(strict_types=1);

final class ScopeChecker
{
    public static function isInScope(string $target, array $inScope, array $outScope): bool
    {
        [$host, $path] = self::splitTarget($target);
        if ($host === '') {
            return false;
        }
        foreach ($outScope as $pattern) {
            if (self::matches($host, $path, (string) $pattern)) {
                return false;
            }
        }
        foreach ($inScope as $pattern) {
            if (self::matches($host, $path, (string) $pattern)) {
                return true;
            }
        }
        return false;
    }

    public static function splitTarget(string $target) : array
    {
        $t = trim($target);
        $t = preg_replace('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', '', $t) ?? $t;
        $slash = strpos($t, '/');
        if ($slash === false) {
            $hostPort = $t;
            $path = '/';
        }
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;
        if ($path === '') {
            $path = '/';
        }
        $colon = strpos($hostPort, ':');
        $host = $colon === false ? $hostPort : substr($hostPort, 0, $colon);
        return [strtolower(trim($host)), $path];
    }

    public static function matches(string $host, string $path, string $pattern): bool
    {
        $p = trim($pattern);
        if ($p === '') {
            return false;
        }
        $slash = strpos($p, '/');
        if($slash === false) {
            $pHost = $p;
            $pPath = null;
        } else {
            $pHost = substr($p, 0, $slash);
            $pPath = substr($p, $slash);
        }
        if (!self::hostMatches($host, strtolower($pHost))) {
            return false;
        }
        if ($pPath === null) {
            return true;
        }
        if (str_ends_with($pPath, '/*')) {
            $prefix = substr($pPath, 0, -1);
            return str_starts_with($path, $prefix);
        }
        return $path === $pPath;
    }

    private static function hostMatches(string $host, string $patternHost): bool
    {
        if (str_starts_with($patternHost, '*.')) {
            $apex = substr($patternHost, 2);
            return $host !== $apex && str_ends_with($host, '.' . $apex);
        }
        return  $host === $patternHost;
    }

}