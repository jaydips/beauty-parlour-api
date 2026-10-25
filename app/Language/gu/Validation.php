<?php

declare(strict_types=1);

// Gujarati validation messages (CodeIgniter core rules). Field names stay as given by the forms.
return [
    // Core Messages
    'noRuleSets'      => 'Validation configuration માં કોઈ rule set નથી.',
    'ruleNotFound'    => '"{0}" માન્ય rule નથી.',
    'groupNotFound'   => '"{0}" validation rules group નથી.',
    'groupNotArray'   => '"{0}" rule group array હોવું જોઈએ.',
    'invalidTemplate' => '"{0}" માન્ય validation template નથી.',

    // Rule Messages
    'alpha'                 => '{field} માં ફક્ત અક્ષરો હોઈ શકે.',
    'alpha_dash'            => '{field} માં ફક્ત અક્ષરો, અંકો, underscore અને dash હોઈ શકે.',
    'alpha_numeric'         => '{field} માં ફક્ત અક્ષરો અને અંકો હોઈ શકે.',
    'alpha_numeric_punct'   => '{field} માં ફક્ત અક્ષરો, અંકો, space અને ~ ! # $ % & * - _ + = | : . હોઈ શકે.',
    'alpha_numeric_space'   => '{field} માં ફક્ત અક્ષરો, અંકો અને space હોઈ શકે.',
    'alpha_space'           => '{field} માં ફક્ત અક્ષરો અને space હોઈ શકે.',
    'decimal'               => '{field} માં દશાંશ સંખ્યા હોવી જોઈએ.',
    'differs'               => '{field} અને {param} અલગ હોવા જોઈએ.',
    'equals'                => '{field} બરાબર આ હોવું જોઈએ: {param}.',
    'exact_length'          => '{field} બરાબર {param} અક્ષરનું હોવું જોઈએ.',
    'field_exists'          => '{field} હોવું જરૂરી છે.',
    'greater_than'          => '{field} {param} થી મોટી સંખ્યા હોવી જોઈએ.',
    'greater_than_equal_to' => '{field} {param} કે તેથી મોટી સંખ્યા હોવી જોઈએ.',
    'hex'                   => '{field} માં ફક્ત hexadecimal અક્ષરો હોઈ શકે.',
    'in_list'               => '{field} આમાંથી એક હોવું જોઈએ: {param}.',
    'integer'               => '{field} પૂર્ણ સંખ્યા હોવી જોઈએ.',
    'is_natural'            => '{field} માં ફક્ત અંકો હોવા જોઈએ.',
    'is_natural_no_zero'    => '{field} માં ફક્ત અંકો હોવા જોઈએ અને તે શૂન્યથી મોટું હોવું જોઈએ.',
    'is_not_unique'         => '{field} પહેલેથી નોંધાયેલું મૂલ્ય હોવું જોઈએ.',
    'is_unique'             => '{field} પહેલેથી નોંધાયેલું છે; બીજું લખો.',
    'less_than'             => '{field} {param} થી નાની સંખ્યા હોવી જોઈએ.',
    'less_than_equal_to'    => '{field} {param} કે તેથી નાની સંખ્યા હોવી જોઈએ.',
    'matches'               => '{field} અને {param} એકસરખા નથી.',
    'max_length'            => '{field} વધુમાં વધુ {param} અક્ષરનું હોઈ શકે.',
    'min_length'            => '{field} ઓછામાં ઓછું {param} અક્ષરનું હોવું જોઈએ.',
    'not_equals'            => '{field} આ હોઈ શકે નહીં: {param}.',
    'not_in_list'           => '{field} આમાંથી કોઈ હોઈ શકે નહીં: {param}.',
    'numeric'               => '{field} માં ફક્ત સંખ્યા હોવી જોઈએ.',
    'regex_match'           => '{field} યોગ્ય ફોર્મેટમાં નથી.',
    'required'              => '{field} ભરવું જરૂરી છે.',
    'required_with'         => '{param} હોય ત્યારે {field} જરૂરી છે.',
    'required_without'      => '{param} ન હોય ત્યારે {field} જરૂરી છે.',
    'string'                => '{field} માન્ય લખાણ હોવું જોઈએ.',
    'timezone'              => '{field} માન્ય timezone હોવું જોઈએ.',
    'valid_base64'          => '{field} માન્ય base64 લખાણ હોવું જોઈએ.',
    'valid_email'           => '{field} માં માન્ય ઇમેઇલ હોવો જોઈએ.',
    'valid_emails'          => '{field} માં બધા ઇમેઇલ માન્ય હોવા જોઈએ.',
    'valid_ip'              => '{field} માં માન્ય IP હોવું જોઈએ.',
    'valid_url'             => '{field} માં માન્ય URL હોવું જોઈએ.',
    'valid_url_strict'      => '{field} માં માન્ય URL હોવું જોઈએ.',
    'valid_date'            => '{field} માં માન્ય તારીખ હોવી જોઈએ.',
    'valid_json'            => '{field} માં માન્ય JSON હોવું જોઈએ.',

    // Credit Cards
    'valid_cc_number' => '{field} માન્ય કાર્ડ નંબર લાગતો નથી.',

    // Files
    'uploaded' => '{field} માન્ય અપલોડ થયેલી ફાઇલ નથી.',
    'max_size' => '{field} ફાઇલ ખૂબ મોટી છે.',
    'is_image' => '{field} માન્ય ઇમેજ ફાઇલ નથી.',
    'mime_in'  => '{field} નો ફાઇલ પ્રકાર માન્ય નથી.',
    'ext_in'   => '{field} નું ફાઇલ extension માન્ય નથી.',
    'max_dims' => '{field} ઇમેજ નથી અથવા ખૂબ મોટી છે.',
    'min_dims' => '{field} ઇમેજ નથી અથવા ખૂબ નાની છે.',
];
