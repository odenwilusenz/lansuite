<?php

/**
 * @param mixed $string
 */
function SanitizeVal($string): float
{
    if ($string === null || $string === '') {
        return 0.0;
    }

    $string = str_replace(',', '.', (string) $string);
    return (float) $string;
}
