<?php

namespace Ahs12\Setanjo\Dashboard;

use Illuminate\Support\Facades\Vite;

/**
 * The dashboard's prebuilt CSS and JS (dist/), inlined into the page.
 *
 * Inlining means nothing has to be published and assets can never be stale after
 * `composer update`. Pages carry the app's CSP nonce when one is set with Vite::useCspNonce().
 */
class Assets
{
    public static function css(): string
    {
        return static::read('dashboard.css');
    }

    public static function js(): string
    {
        // Keep a literal "</script>" inside the bundle from closing the inline tag.
        return str_replace('</script', '<\/script', static::read('dashboard.js'));
    }

    public static function nonce(): ?string
    {
        return Vite::cspNonce();
    }

    protected static function read(string $file): string
    {
        $path = dirname(__DIR__, 2).'/dist/'.$file;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
