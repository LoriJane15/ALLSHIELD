<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class Ib39CdrNarrativeFormatter
{
    public static function render(?string $source): HtmlString
    {
        $lines = preg_split('/\R/u', trim((string) $source)) ?: [];
        $html = '';
        $paragraph = [];
        $inList = false;
        $flush = function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html .= '<p>'.implode('<br>', array_map(self::inline(...), $paragraph)).'</p>';
                $paragraph = [];
            }
        };
        foreach ($lines as $line) {
            if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $match)) {
                $flush();
                if (! $inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>'.self::inline($match[1]).'</li>';

                continue;
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            trim($line) === '' ? $flush() : $paragraph[] = $line;
        }
        $flush();
        if ($inList) {
            $html .= '</ul>';
        }

        return new HtmlString($html ?: '<p>—</p>');
    }

    private static function inline(string $source): string
    {
        $escaped = e($source);

        return preg_replace('/\*\*(?=\S)(.+?\S)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
    }
}
