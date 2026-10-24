<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

/**
 * Display helpers for the web UI (autoloaded via Config\Autoload::$helpers).
 * Labels are Gujarati; numbers, dates and times use English digits.
 */

if (! function_exists('rupees')) {
    function rupees(int|float|string|null $amount): string
    {
        return '₹' . number_format((float) ($amount ?? 0), 0, '.', ',');
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(?string $dateTime): string
    {
        return $dateTime ? Time::parse($dateTime)->format('d M Y') : '';
    }
}

if (! function_exists('fmt_time')) {
    function fmt_time(?string $dateTime): string
    {
        return $dateTime ? Time::parse($dateTime)->format('h:i A') : '';
    }
}

if (! function_exists('fmt_datetime')) {
    function fmt_datetime(?string $dateTime): string
    {
        return $dateTime ? Time::parse($dateTime)->format('d M Y, h:i A') : '';
    }
}

if (! function_exists('fmt_day_title')) {
    /** "આજે", "કાલે", "ગઈકાલે" or the date, for day headers. */
    function fmt_day_title(string $date): string
    {
        $today = Time::now()->toDateString();

        return match ($date) {
            $today                                           => 'આજે, ' . Time::parse($date)->format('d M'),
            Time::now()->addDays(1)->toDateString()         => 'કાલે, ' . Time::parse($date)->format('d M'),
            Time::now()->subDays(1)->toDateString()         => 'ગઈકાલે, ' . Time::parse($date)->format('d M'),
            default                                          => Time::parse($date)->format('D, d M Y'),
        };
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'નક્કી',
            'confirmed' => 'Confirm',
            'completed' => 'પૂરું',
            'cancelled' => 'રદ',
            'no_show'   => 'ન આવ્યા',
            'pending'   => 'બાકી',
            'sent'      => 'મોકલ્યું',
            'skipped'   => 'છોડ્યું',
            default     => (string) $status,
        };
    }
}

if (! function_exists('status_class')) {
    function status_class(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'badge-blue',
            'confirmed' => 'badge-teal',
            'completed' => 'badge-green',
            'cancelled', 'no_show' => 'badge-grey',
            'pending'   => 'badge-amber',
            'sent'      => 'badge-green',
            default     => 'badge-grey',
        };
    }
}

if (! function_exists('reminder_type_label')) {
    function reminder_type_label(?string $type): string
    {
        return match ($type) {
            'appointment' => 'Appointment reminder',
            'follow_up'   => 'Follow-up',
            'birthday'    => 'જન્મદિવસ',
            'custom'      => 'Custom',
            default       => (string) $type,
        };
    }
}

if (! function_exists('method_label')) {
    function method_label(?string $method): string
    {
        return match ($method) {
            'cash'  => 'Cash',
            'upi'   => 'UPI',
            'card'  => 'Card',
            'other' => 'બીજું',
            default => (string) $method,
        };
    }
}

if (! function_exists('message_status_label')) {
    /** Gujarati label for an outbox message status. */
    function message_status_label(?string $status): string
    {
        return match ($status) {
            'queued'    => 'કતારમાં',
            'sent'      => 'મોકલાયું',
            'failed'    => 'નિષ્ફળ',
            'cancelled' => 'રદ',
            default     => (string) $status,
        };
    }
}

if (! function_exists('message_status_class')) {
    function message_status_class(?string $status): string
    {
        return match ($status) {
            'queued'    => 'badge-amber',
            'sent'      => 'badge-green',
            'failed'    => 'badge-grey',
            'cancelled' => 'badge-grey',
            default     => 'badge-blue',
        };
    }
}

if (! function_exists('message_type_label')) {
    /** Gujarati label for an outbox message type. */
    function message_type_label(?string $type): string
    {
        return match ($type) {
            'booking'     => 'Booking confirmation',
            'appointment' => 'Appointment reminder',
            'follow_up'   => 'Follow-up',
            'birthday'    => 'જન્મદિવસ',
            'offer'       => 'Offer',
            'custom'      => 'Custom',
            default       => (string) $type,
        };
    }
}

if (! function_exists('campaign_status_label')) {
    function campaign_status_label(?string $status): string
    {
        return match ($status) {
            'queued'    => 'મોકલાઈ રહ્યું છે',
            'done'      => 'પૂર્ણ',
            'cancelled' => 'રદ',
            default     => (string) $status,
        };
    }
}
