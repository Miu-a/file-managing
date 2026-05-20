<?php declare(strict_types=1);

namespace Optimal\FileManaging\Utils;

class SystemPaths
{

    public static ?string $absolutePath = null;

    public static function getScriptPath(): string
    {
        if (!is_null(self::$absolutePath)) {
            return self::$absolutePath;
        }
        if (empty(strrpos(self::getAbsoluteScriptName(), "/"))) {
            return "";
        }
        return substr(self::getAbsoluteScriptName(), 0, strrpos(self::getAbsoluteScriptName(), "/"));
    }

    public static function getAbsoluteScriptName(): string
    {
        return str_replace("\\", "/", $_SERVER["SCRIPT_FILENAME"]);
    }

    public static function getRelativeScriptName(): string
    {
        return str_replace("\\", "/", $_SERVER["SCRIPT_NAME"]);
    }

    public static function getBaseUrl(): string
    {
        return self::getUrlDomain() . substr(self::getRelativeScriptName(), 0, strrpos(self::getRelativeScriptName(), "/"));
    }

    public static function getUrlDomain(): string
    {
        $url = self::fullUrl($_SERVER);
        $url = str_replace("//", "*", $url);
        $url = explode("/", $url);
        return str_replace("*", "//", $url[0]);
    }

    public static function fullUrl(array $s, bool $use_forwarded_host = false): string
    {
        $ssl = !empty($s['HTTPS']) && $s['HTTPS'] === 'on';
        $sp = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . ($ssl ? 's' : '');
        $port = $s['SERVER_PORT'];
        $port = ((!$ssl && $port === '80') || ($ssl && $port === '443')) ? '' : ':' . $port;
        $host = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST'])) ? $s['HTTP_X_FORWARDED_HOST'] : ($s['HTTP_HOST'] ?? null);
        $host = $host ?? $s['SERVER_NAME'] . $port;

        return $protocol . '://' . $host . $s['REQUEST_URI'];
    }
}