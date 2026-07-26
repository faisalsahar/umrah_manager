<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available Interface Languages
    |--------------------------------------------------------------------------
    |
    | Shown in the language settings screen. Keys must match a lang/{key}.json
    | translation file. 'rtl' controls the <html dir="…"> attribute. 'flag' is
    | a decorative emoji shown above the language name in the switcher.
    |
    */

    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false, 'flag' => '🇬🇧'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true, 'flag' => '🇸🇦'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth Screen Quotes
    |--------------------------------------------------------------------------
    |
    | Sentences the auth branding panel types out one at a time, picking the
    | next at random. They are the first thing a signing-in user reads.
    | An empty array hides the rotating block entirely. Keyed by locale so the
    | typewriter shows a native-language sentence rather than a translation.
    |
    */

    'auth_quotes' => [
        'en' => [
            'Manage every Umrah group from one place pilgrims, packages, visas, and departures.',
            'Track each pilgrim from booking through to return, with documents and status always current.',
            'Build Umrah packages that bundle visas, flights, hotels, and ground transport.',
            'Keep Makkah and Madinah accommodation, room allocation, and check-in dates in order.',
            'Coordinate transport and ziyarat schedules so every group moves on time.',
            'Handle visa applications and passport records without losing track of a single pilgrim.',
            'Give your agents and branches one shared view of every booking and every payment.',
            'Record payments, balances, and invoices against each booking as the group fills up.',
        ],
        'ar' => [
            'أدر كل مجموعة عمرة من مكان واحد: المعتمرين والباقات والتأشيرات والمغادرات.',
            'تابع كل معتمر من الحجز حتى العودة، مع بقاء المستندات والحالة محدّثة دائمًا.',
            'أنشئ باقات عمرة تجمع التأشيرات والرحلات والفنادق والنقل البري.',
            'حافظ على تنظيم سكن مكة والمدينة وتوزيع الغرف ومواعيد تسجيل الوصول.',
            'نسّق جداول النقل والزيارات لتتحرك كل مجموعة في وقتها.',
            'أدر طلبات التأشيرات وسجلات جوازات السفر دون أن يغيب عنك معتمر واحد.',
            'امنح وكلاءك وفروعك رؤية مشتركة لكل حجز وكل دفعة.',
            'سجّل المدفوعات والأرصدة والفواتير لكل حجز أثناء اكتمال المجموعة.',
        ],
    ],

];
