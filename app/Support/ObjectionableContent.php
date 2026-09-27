<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ObjectionableContent
{
    /**
     * Block clearly illegal or abusive text before it is posted.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '/\b(child\s*porn|child\s*sex|csam|underage\s*sex|preteen\s*sex)\b/i',
        '/\b(nigger|nigga|faggot|kike|retard)\b/i',
        '/\b(kill\s*yourself|kys)\b/i',
    ];

    public static function assertClean(?string ...$parts): void
    {
        $text = trim(implode("\n", array_filter(
            $parts,
            fn ($part) => is_string($part) && trim($part) !== '',
        )));

        if ($text === '') {
            return;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                throw ValidationException::withMessages([
                    'body' => ['This content is not allowed. Remove offensive, abusive, or illegal material and try again.'],
                ]);
            }
        }
    }
}
