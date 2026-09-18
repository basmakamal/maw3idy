<?php

/*
 * Only the rules this application actually uses. Laravel falls back to the
 * English message for anything missing, which is easier to notice than a
 * wall of translations nobody reads.
 */

return [

    'after_or_equal' => 'يجب أن يكون :attribute بعد :date أو مساوياً له.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'before_or_equal' => 'يجب أن يكون :attribute قبل :date أو مساوياً له.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'date' => ':attribute ليس تاريخاً صحيحاً.',
    'date_format' => 'لا يطابق :attribute الصيغة :format.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'exists' => ':attribute المختار غير صحيح.',
    'in' => ':attribute المختار غير صحيح.',
    'integer' => 'يجب أن يكون :attribute رقماً صحيحاً.',
    'not_in' => ':attribute المختار غير صحيح.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'unique' => ':attribute مستخدم من قبل.',

    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يكون حجم :attribute أكبر من :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string' => 'يجب ألا يكون :attribute أطول من :max حرفاً.',
    ],

    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يكون حجم :attribute أقل من :min كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أقل من :min.',
        'string' => 'يجب أن يكون :attribute :min أحرف على الأقل.',
    ],

    'attributes' => [
        'business_name' => 'اسم النشاط',
        'customerEmail' => 'البريد الإلكتروني',
        'customerName' => 'الاسم',
        'customerPhone' => 'رقم الجوال',
        'date' => 'اليوم',
        'email' => 'البريد الإلكتروني',
        'form.buffer_after_minutes' => 'وقت الاستعداد',
        'form.duration_minutes' => 'المدة',
        'form.email' => 'البريد الإلكتروني',
        'form.name' => 'الاسم',
        'form.price' => 'السعر',
        'locale' => 'اللغة',
        'name' => 'الاسم',
        'offEnd' => 'نهاية الإجازة',
        'offReason' => 'السبب',
        'offStart' => 'بداية الإجازة',
        'password' => 'كلمة المرور',
        'reason' => 'السبب',
        'slug' => 'عنوان صفحة الحجز',
        'timezone' => 'المنطقة الزمنية',
    ],

];
