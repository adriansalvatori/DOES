<?php

namespace App\Support;

class CompactHtml
{
    /**
     * Collapses indentation and line breaks outside inline scripts so large Livewire views carry less whitespace.
     */
    public static function compact(string $html): string
    {
        $parts = preg_split('#(<script\b.*?</script>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = preg_replace(['/\s*\n\s*/', '/[ \t]{2,}/'], ' ', $part);
            }
        }

        return implode('', $parts);
    }
}
