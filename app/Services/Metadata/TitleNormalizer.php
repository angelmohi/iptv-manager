<?php

namespace App\Services\Metadata;

class TitleNormalizer
{
    private const NOISE_TOKENS = [
        '4K', 'UHD', 'HD', 'FHD', 'SD', 'HDR', 'DOLBY', 'DV',
        'LATINO', 'CASTELLANO', 'ESPAÑOL', 'ESPANOL', 'VOSE', 'VO', 'SUB',
        'MULTI', 'DUAL', 'IMAX', 'EXTENDED', 'DIRECTORS', 'CUT',
        'REMUX', 'WEB', 'WEBDL', 'WEB-DL', 'BLURAY', 'BDRIP',
    ];

    /**
     * Returns ['title' => string, 'year' => int|null].
     *
     * Handles the pipe-separated format used in this catalogue:
     *   "Title (129min) | EE.UU y R.U - 2013 | +16 | ★3.9/5"
     */
    public static function parse(string $raw): array
    {
        $name = trim($raw);
        $year = null;

        // ── Pipe-separated format ──────────────────────────────────────────────
        // "Title (Xmin) | Countries - Year | AgeRating | StarRating"
        if (str_contains($name, '|')) {
            $parts     = explode('|', $name);
            $titlePart = trim($parts[0]);

            // Extract year from the second segment: "EE.UU y R.U - 2013"
            if (isset($parts[1]) && preg_match('/[-–]\s*((?:19|20)\d{2})\s*$/', trim($parts[1]), $m)) {
                $year = (int) $m[1];
            }

            // Strip duration "(129min)", "(1h 45min)", etc. from the title part
            $titlePart = preg_replace('/\(\s*(?:\d+h\s*)?\d+\s*min\s*\)/iu', '', $titlePart);

            // Strip any remaining parenthetical/bracket metadata tags: platform names,
            // channel labels, language flags, etc. — e.g. "(Sky Show)", "[Netflix]", "{VF}".
            // In the pipe-separated catalogue format the title segment never carries
            // legitimate parenthetical content that belongs to the actual title.
            $titlePart = preg_replace('/[\(\[\{][^\)\]\}]+[\)\]\}]/u', ' ', $titlePart);

            // Remove stray star/emoji ratings that might sit inside the title part
            $titlePart = preg_replace('/[★☆⭐]\s*[\d,.]+\s*\/\s*\d+/u', '', $titlePart);

            $name = trim(preg_replace('/\s{2,}/u', ' ', $titlePart), " \t\n\r\0\x0B-_.,;:|");

            return ['title' => $name, 'year' => $year];
        }

        // ── Generic fallback (non-pipe format) ────────────────────────────────

        // Strip bracket/parenthesis content, capture year from inside
        $name = preg_replace_callback(
            '/[\[\(\{]([^\]\)\}]+)[\]\)\}]/u',
            function ($m) use (&$year) {
                // Only capture year from brackets; ignore duration-like "(129min)"
                if ($year === null
                    && !preg_match('/\d+\s*min/i', $m[1])
                    && preg_match('/\b(19|20)\d{2}\b/', $m[1], $ym)
                ) {
                    $year = (int) $ym[0];
                }
                return ' ';
            },
            $name
        );

        // Capture free-standing year and remove it — but only if the number
        // does NOT appear to be part of the title (e.g. "2001: Una odisea")
        // Heuristic: a year preceded by a colon or at the very start is part of the title.
        if ($year === null) {
            $name = preg_replace_callback(
                '/(?<![:\d])\b((?:19|20)\d{2})\b(?![\d:])/u',
                function ($m) use (&$year) {
                    if ($year === null) {
                        $year = (int) $m[1];
                        return ' ';
                    }
                    return $m[0];
                },
                $name
            );
        }

        // Series markers — episode codes first
        $name = preg_replace('/\b[Ss]\d{1,2}[Ee]\d{1,3}\b/u', ' ', $name);
        $name = preg_replace('/\b\d{1,2}x\d{1,3}\b/u', ' ', $name);

        // Season/temporada keywords in several languages — allow zero whitespace
        // between the keyword and the number (e.g. "Season2" with no space).
        // The leading "[,\s]?" eats a trailing comma from things like
        // "30 Monedas, Season2" so the previous word is not glued to the digit.
        $name = preg_replace(
            '/[,\s]?\b(?:Season|Saison|Stagione|Temporada|Sezon|Sezona|Staffel)\s*\d+\b/iu',
            ' ',
            $name
        );

        // Spanish/Portuguese shorthand (T1, T01, T1E5)
        $name = preg_replace('/\bT\d{1,2}(?:\s*E\d{1,3})?\b/iu', ' ', $name);

        // Standalone S1/S01 (without an episode marker that would have matched above)
        $name = preg_replace('/\b[Ss]\d{1,2}\b(?![Ee]\d)/u', ' ', $name);

        $name = preg_replace('/\bCap(?:ítulo|itulo)?\s+\d+\b/iu', ' ', $name);

        // Noise tokens
        $pattern = '/\b(' . implode('|', array_map('preg_quote', self::NOISE_TOKENS)) . ')\b/iu';
        $name = preg_replace($pattern, ' ', $name);

        // Separators
        $name = preg_replace('/[_\.\-:|]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $name = trim($name, " \t\n\r\0\x0B-_.,;:|");

        // Year-as-title rescue: if the year regex above swallowed the entire
        // title (real cases: "1923", "2001", "1984"), the providers would
        // receive an empty query. Put the digits back as the title and clear
        // the year so the search proceeds.
        if ($name === '' && $year !== null) {
            $name = (string) $year;
            $year = null;
        }

        return ['title' => $name, 'year' => $year];
    }
}
