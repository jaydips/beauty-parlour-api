<?php

declare(strict_types=1);

/**
 * Display helpers for the Gujarati web UI (loaded by App\Controllers\Web\WebController).
 * Labels are Gujarati; numerals, dates and amounts stay in English digits (owner decision).
 */

use CodeIgniter\I18n\Time;

if (! function_exists('money')) {
    /**
     * Whole rupees with Indian digit grouping: 1600 -> "₹1,600", 125000 -> "₹1,25,000".
     */
    function money(int|float|string|null $amount, bool $symbol = true): string
    {
        $n    = (int) round((float) ($amount ?? 0));
        $sign = $n < 0 ? '-' : '';
        $s    = (string) abs($n);

        if (strlen($s) > 3) {
            $last  = substr($s, -3);
            $rest  = substr($s, 0, -3);
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s     = $rest . ',' . $last;
        }

        return $sign . ($symbol ? '₹' : '') . $s;
    }
}

if (! function_exists('gu_weekday')) {
    /**
     * Short Gujarati weekday name for a date/time string.
     */
    function gu_weekday(string $dateTime): string
    {
        $names = ['Sun' => 'રવિ', 'Mon' => 'સોમ', 'Tue' => 'મંગળ', 'Wed' => 'બુધ', 'Thu' => 'ગુરુ', 'Fri' => 'શુક્ર', 'Sat' => 'શનિ'];

        return $names[Time::parse($dateTime)->format('D')] ?? '';
    }
}

if (! function_exists('fmt_date')) {
    /**
     * "24 Oct 2026" (optionally with the Gujarati weekday: "શુક્ર, 24 Oct 2026").
     */
    function fmt_date(?string $dateTime, bool $weekday = false): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '-';
        }

        $t = Time::parse($dateTime);

        return ($weekday ? gu_weekday($dateTime) . ', ' : '') . $t->format('d M Y');
    }
}

if (! function_exists('fmt_time')) {
    /**
     * "10:00 AM"
     */
    function fmt_time(?string $dateTime): string
    {
        return ($dateTime === null || $dateTime === '') ? '-' : Time::parse($dateTime)->format('h:i A');
    }
}

if (! function_exists('fmt_dt')) {
    /**
     * "24 Oct, 10:00 AM" (year added when it differs from the current year).
     */
    function fmt_dt(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '-';
        }

        $t = Time::parse($dateTime);

        return $t->format($t->getYear() === Time::now()->getYear() ? 'd M, h:i A' : 'd M Y, h:i A');
    }
}

if (! function_exists('day_title')) {
    /**
     * "આજે", "કાલે", "ગઈકાલે" or the weekday + date.
     */
    function day_title(string $date): string
    {
        $today = Time::now()->toDateString();

        return match ($date) {
            $today                                           => 'આજે, ' . fmt_date($date),
            Time::now()->addDays(1)->toDateString()          => 'કાલે, ' . fmt_date($date),
            Time::now()->subDays(1)->toDateString()          => 'ગઈકાલે, ' . fmt_date($date),
            default                                          => fmt_date($date, true),
        };
    }
}

if (! function_exists('status_label')) {
    /**
     * Gujarati label for an appointment status.
     */
    function status_label(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'શેડ્યૂલ',
            'confirmed' => 'કન્ફર્મ',
            'completed' => 'પૂર્ણ',
            'cancelled' => 'રદ',
            'no_show'   => 'આવ્યા નહીં',
            default     => (string) $status,
        };
    }
}

if (! function_exists('status_badge')) {
    /**
     * <span class="badge badge-<status>">label</span>
     */
    function status_badge(?string $status): string
    {
        return '<span class="badge badge-' . esc((string) $status, 'attr') . '">' . esc(status_label($status)) . '</span>';
    }
}

if (! function_exists('reminder_status_label')) {
    function reminder_status_label(?string $status): string
    {
        return match ($status) {
            'pending'   => 'બાકી',
            'sent'      => 'મોકલાયું',
            'skipped'   => 'છોડ્યું',
            'cancelled' => 'રદ',
            default     => (string) $status,
        };
    }
}

if (! function_exists('reminder_type_label')) {
    function reminder_type_label(?string $type): string
    {
        return match ($type) {
            'appointment' => 'અપોઇન્ટમેન્ટ રિમાઇન્ડર',
            'follow_up'   => 'ફોલો-અપ',
            'birthday'    => 'જન્મદિવસ',
            'custom'      => 'કસ્ટમ મેસેજ',
            default       => (string) $type,
        };
    }
}

if (! function_exists('method_label')) {
    /**
     * Payment method label.
     */
    function method_label(?string $method): string
    {
        return match ($method) {
            'cash'  => 'રોકડ',
            'upi'   => 'UPI',
            'card'  => 'કાર્ડ',
            'other' => 'અન્ય',
            default => (string) $method,
        };
    }
}

if (! function_exists('source_label')) {
    function source_label(?string $source): string
    {
        return match ($source) {
            'walk_in'  => 'વૉક-ઇન',
            'phone'    => 'ફોન',
            'whatsapp' => 'WhatsApp',
            'app'      => 'એપ',
            default    => (string) $source,
        };
    }
}

if (! function_exists('gender_label')) {
    function gender_label(?string $gender): string
    {
        return match ($gender) {
            'female' => 'સ્ત્રી',
            'male'   => 'પુરુષ',
            'other'  => 'અન્ય',
            default  => (string) $gender,
        };
    }
}

if (! function_exists('items_summary')) {
    /**
     * "Haircut, Facial ×2" from appointment line items.
     *
     * @param list<array<string, mixed>> $items
     */
    function items_summary(array $items): string
    {
        $parts = [];

        foreach ($items as $item) {
            $qty     = (int) ($item['quantity'] ?? 1);
            $parts[] = (string) $item['service_name'] . ($qty > 1 ? ' ×' . $qty : '');
        }

        return implode(', ', $parts);
    }
}

if (! function_exists('wa_chat_link')) {
    /**
     * Plain click-to-chat link (no prefilled text) for a 10-digit customer phone.
     */
    function wa_chat_link(?string $phone, string $text = ''): string
    {
        return service('whatsapp')->link((string) $phone, $text);
    }
}

if (! function_exists('tel_link')) {
    /**
     * tel: link with the country code from settings (default 91).
     */
    function tel_link(?string $phone): string
    {
        $cc = preg_replace('/\D+/', '', (string) model(\App\Models\SettingModel::class)->value('country_code', '91')) ?: '91';

        return 'tel:+' . $cc . preg_replace('/\D+/', '', (string) $phone);
    }
}

if (! function_exists('field_error')) {
    /**
     * Renders the error of one form field, if any.
     *
     * @param array<string, string> $errors
     */
    function field_error(array $errors, string $field): string
    {
        return isset($errors[$field]) ? '<div class="field-error">' . esc($errors[$field]) . '</div>' : '';
    }
}

if (! function_exists('selected_if')) {
    function selected_if(mixed $a, mixed $b): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }
}

if (! function_exists('checked_if')) {
    function checked_if(bool $cond): string
    {
        return $cond ? ' checked' : '';
    }
}
