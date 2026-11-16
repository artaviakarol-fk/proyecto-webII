<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Domain\Services;

/**
 * Finds the name of the negocio that best matches a name the cashier wrote, ignoring case, accents,
 * plurals and extra words: "2 leches" matches "Leche entera 1 L" and "doña maria" matches "Doña María".
 * Only names of the negocio are candidates, so the draft never points to anything that does not exist.
 */
final class NameMatcher
{
    /** Below this share of matching words, a name is not considered a match. */
    public const float THRESHOLD = 0.5;

    /**
     * The best candidate for a written name, or null when none is close enough.
     *
     * @template T
     *
     * @param  array<string, T>  $candidates  name => candidate
     * @return array{0: T, 1: bool}|null the candidate, and whether another one matched just as many words (ambiguous)
     */
    public static function best(string $written, array $candidates): ?array
    {
        $wanted = self::words($written);

        if ($wanted === []) {
            return null;
        }

        $scores = [];

        foreach ($candidates as $name => $candidate) {
            $share = self::share($wanted, self::words((string) $name));

            if ($share >= self::THRESHOLD) {
                similar_text(self::normalize($written), self::normalize((string) $name), $likeness);
                $scores[] = [$share, $likeness, $candidate];
            }
        }

        if ($scores === []) {
            return null;
        }

        usort($scores, fn (array $a, array $b): int => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        $tied = isset($scores[1]) && abs($scores[0][0] - $scores[1][0]) < 0.0001;

        return [$scores[0][2], $tied];
    }

    /**
     * Share of the written words found in the name; the overall likeness only orders names with the same share.
     *
     * @param  list<string>  $wanted
     * @param  list<string>  $words
     */
    private static function share(array $wanted, array $words): float
    {
        $found = 0;

        foreach ($wanted as $word) {
            if (array_any($words, fn (string $candidate): bool => self::sameWord($word, $candidate))) {
                $found++;
            }
        }

        return $found / count($wanted);
    }

    /** Two words match when they are equal, differ only in a final "e" (pan, pane) or one starts the other with at least 5 letters (frijol, frijoles). */
    private static function sameWord(string $a, string $b): bool
    {
        if ($a === $b || $a.'e' === $b || $b.'e' === $a) {
            return true;
        }

        $shorter = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;

        return mb_strlen($shorter) >= 5 && str_starts_with($longer, $shorter);
    }

    /**
     * The meaningful words of a name, without accents, plurals or filler words.
     *
     * @return list<string>
     */
    private static function words(string $value): array
    {
        $stop = ['de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'con', 'don', 'dona', 'sr', 'sra'];
        $words = [];

        foreach (preg_split('/\s+/', self::normalize($value)) ?: [] as $word) {
            if ($word === '' || in_array($word, $stop, true) || ctype_digit($word)) {
                continue;
            }

            $words[] = self::singular($word);
        }

        return $words;
    }

    /** Lowercase, without accents and keeping only letters, digits and spaces. */
    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', $value));
    }

    /** A simple Spanish singular: "leches" -> "leche", "arroces" -> "arroz"; "panes" -> "pane", which sameWord matches with "pan". */
    private static function singular(string $word): string
    {
        if (mb_strlen($word) > 4 && str_ends_with($word, 'ces')) {
            return substr($word, 0, -3).'z';
        }

        return mb_strlen($word) > 3 && str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
    }
}
