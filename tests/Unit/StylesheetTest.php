<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Hlídá, že v CSS sedí složené závorky. Jedna „}“ navíc tiše vyřadí následující pravidlo
 * (stalo se: rozbalovací menu řazení ztratilo position: relative a vypadlo pod patičku).
 */
final class StylesheetTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function stylesheets(): iterable
    {
        foreach (glob(dirname(__DIR__, 2) . '/public/assets/css/*.css') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    /** @dataProvider stylesheets */
    public function testBracesAreBalanced(string $file): void
    {
        $css = (string) file_get_contents($file);
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = (string) preg_replace('~"[^"]*"|\'[^\']*\'~', '', $css);

        $depth = 0;
        $line = 1;
        foreach (str_split($css) as $char) {
            if ($char === "\n") {
                $line++;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                self::assertGreaterThanOrEqual(0, $depth, basename($file) . ": nadbytečná „}“ kolem řádku {$line} (bez komentářů)");
            }
        }
        self::assertSame(0, $depth, basename($file) . ': neuzavřený blok „{“');
    }
}
