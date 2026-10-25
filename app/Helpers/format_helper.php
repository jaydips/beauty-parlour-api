<?php

declare(strict_types=1);

use CodeIgniter\I18n\Time;

/**
 * Display helpers for the web UI (autoloaded via Config\Autoload::$helpers).
 * Everything the owner reads is Gujarati (app/Language/gu/App.php); digits stay ASCII.
 * Dates: "25 ઑક્ટો 2026", day headers "આજે, 25 ઑક્ટો", times "સવારે 10:30".
 */

if (! function_exists('rupees')) {
    function rupees(int|float|string|null $amount): string
    {
        return '₹' . number_format((float) ($amount ?? 0), 0, '.', ',');
    }
}

if (! function_exists('gu_time')) {
    /** 12-hour clock with a Gujarati time-of-day word: "સવારે 10:30", "બપોરે 2:00", "સાંજે 6:30", "રાત્રે 9:00". */
    function gu_time(Time $time): string
    {
        $hour   = (int) $time->format('G');
        $period = match (true) {
            $hour < 4   => 'night',
            $hour < 12  => 'morning',
            $hour < 16  => 'afternoon',
            $hour < 20  => 'evening',
            default     => 'night',
        };

        return lang('App.period_' . $period) . ' ' . $time->format('g:i');
    }
}

if (! function_exists('gu_date')) {
    /** "25 ઑક્ટો 2026" (short) or "25 ઑક્ટોબર 2026" (long); $withDay prefixes the weekday ("શનિવાર, 25 ઑક્ટોબર 2026"). */
    function gu_date(Time $time, bool $long = false, bool $withDay = false, bool $withYear = true): string
    {
        $months = lang($long ? 'App.months_long' : 'App.months_short');
        $days   = lang($long ? 'App.days_long' : 'App.days_short');
        $out    = (int) $time->format('j') . ' ' . $months[(int) $time->format('n') - 1] . ($withYear ? ' ' . $time->format('Y') : '');

        return $withDay ? $days[(int) $time->format('w')] . ', ' . $out : $out;
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(?string $dateTime): string
    {
        return $dateTime ? gu_date(Time::parse($dateTime)) : '';
    }
}

if (! function_exists('fmt_time')) {
    function fmt_time(?string $dateTime): string
    {
        return $dateTime ? gu_time(Time::parse($dateTime)) : '';
    }
}

if (! function_exists('fmt_datetime')) {
    function fmt_datetime(?string $dateTime): string
    {
        if (! $dateTime) {
            return '';
        }

        $time = Time::parse($dateTime);

        return gu_date($time) . ', ' . gu_time($time);
    }
}

if (! function_exists('fmt_day_title')) {
    /** "આજે, 25 ઑક્ટો", "કાલે, …", "ગઈકાલે, …" or "શનિ, 25 ઑક્ટો 2026", for day headers. */
    function fmt_day_title(string $date): string
    {
        $day = Time::parse($date);
        $now = Time::now();

        return match ($date) {
            $now->toDateString()             => lang('App.today') . ', ' . gu_date($day, false, false, false),
            $now->addDays(1)->toDateString() => lang('App.tomorrow') . ', ' . gu_date($day, false, false, false),
            $now->subDays(1)->toDateString() => lang('App.yesterday') . ', ' . gu_date($day, false, false, false),
            default                          => gu_date($day, false, true),
        };
    }
}

if (! function_exists('enum_label')) {
    /** lang('App.<prefix>_<value>') when the key exists, else the raw value (unknown enum values stay readable). */
    function enum_label(string $prefix, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $key   = 'App.' . $prefix . '_' . $value;
        $label = lang($key);

        return is_string($label) && $label !== $key ? $label : $value;
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return enum_label('status', $status);
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
        return enum_label('reminder_type', $type);
    }
}

if (! function_exists('method_label')) {
    function method_label(?string $method): string
    {
        return enum_label('method', $method);
    }
}

if (! function_exists('source_label')) {
    function source_label(?string $source): string
    {
        return enum_label('source', $source);
    }
}

if (! function_exists('message_status_label')) {
    /** Gujarati label for an outbox message status. */
    function message_status_label(?string $status): string
    {
        return enum_label('msg_status', $status);
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
        return enum_label('msg_type', $type);
    }
}

if (! function_exists('campaign_status_label')) {
    function campaign_status_label(?string $status): string
    {
        return enum_label('campaign_status', $status);
    }
}

if (! function_exists('campaign_status_class')) {
    function campaign_status_class(?string $status): string
    {
        return match ($status) {
            'done'      => 'badge-green',
            'cancelled' => 'badge-grey',
            default     => 'badge-amber',
        };
    }
}
