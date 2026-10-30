<?php
/************************************************************************
 * This file is part of the Calendar Feed extension for EspoCRM.
 *
 * Copyright (c) 2026 Lucas Neves
 *
 * This extension is licensed under the MIT License.
 * EspoCRM itself is licensed under the GNU Affero General Public License v3.
 ************************************************************************/

declare(strict_types=1);

namespace Espo\Modules\CalendarFeed\Tools\Ics;

/**
 * Turns stored HTML meeting descriptions into plain text for a VEVENT.
 */
final class HtmlToText
{
    public function convert(string $value): string
    {
        if (!preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $value)) {
            return $value;
        }

        $value = preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $value) ?? $value;
        $value = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $value) ?? $value;
        $value = preg_replace('/<\s*\/\s*(p|div|tr|h[1-6]|li|blockquote|table)\s*>/i', "\n", $value) ?? $value;
        $value = preg_replace('/<\s*li\b[^>]*>/i', "\n- ", $value) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }
}
