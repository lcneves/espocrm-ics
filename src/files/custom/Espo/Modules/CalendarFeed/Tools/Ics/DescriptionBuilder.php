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
 * Appends a readable participant list to the meeting description.
 */
final class DescriptionBuilder
{
    public function __construct(private HtmlToText $htmlToText) {}

    /**
     * @param list<Participant> $participants
     */
    public function build(?string $description, array $participants): string
    {
        $text = $this->htmlToText->convert($description ?? '');
        $lines = [];

        foreach ($participants as $participant) {
            $lines[] = '- ' . $this->formatParticipant($participant);
        }

        $block = "Participants:\n" . implode("\n", $lines);

        if (trim($text) === '') {
            return $block;
        }

        return rtrim($text) . "\n\n" . $block;
    }

    private function formatParticipant(Participant $participant): string
    {
        $label = $participant->assigned ? 'Assigned User' : $participant->entityType;
        $status = $this->statusLabel($participant->status);

        if ($status !== null) {
            $label .= ', ' . $status;
        }

        $line = $participant->name . ' (' . $label . ')';

        if ($participant->email !== null && $participant->email !== '') {
            $line .= ' — ' . $participant->email;
        }

        return $line;
    }

    private function statusLabel(?string $status): ?string
    {
        return match ($status) {
            'Accepted', 'Tentative', 'Declined' => $status,
            default => null,
        };
    }
}
