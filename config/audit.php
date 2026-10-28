<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),

    // কত মাস পরে পুরোনো লগ সরানো হবে। 0 দিলে চিরকাল রাখা হবে।
    'retention_months' => (int) env('AUDIT_RETENTION_MONTHS', 36),

    'types' => [
        'Farm' => 'খামার',
        'Cow' => 'গরু',
        'Customer' => 'গ্রাহক',
        'Expense' => 'খরচ',
        'Sale' => 'বিক্রি',
        'Payment' => 'পেমেন্ট',
        'Subscription' => 'সাবস্ক্রিপশন',
        'SubscriptionPayment' => 'সাবস্ক্রিপশনের পেমেন্ট',
        'Plan' => 'প্ল্যান',
        'FarmMember' => 'সদস্য',
        'Report' => 'রিপোর্ট',
        'User' => 'ব্যবহারকারী',
    ],

    'events' => [
        'created' => 'যোগ হয়েছে',
        'updated' => 'বদলানো হয়েছে',
        'deleted' => 'মোছা হয়েছে',
        'restored' => 'ফিরিয়ে আনা হয়েছে',
        'voided' => 'বাতিল করা হয়েছে',
        'exported' => 'ডাউনলোড করা হয়েছে',
        'login' => 'লগইন করেছে',
        'login_failed' => 'লগইনে ভুল করেছে',
        'lockout' => 'লগইন আটকে গেছে',
        'password_reset' => 'পাসওয়ার্ড বদলেছে',
        'imported' => 'ইমপোর্ট করা হয়েছে',
    ],

    // বিস্তারিত অংশে ক্ষেত্রের নাম। এখানে না থাকলে মূল নামই দেখানো হয়।
    'fields' => [
        'name' => 'নাম',
        'status' => 'অবস্থা',
        'role' => 'ভূমিকা',
        'user_id' => 'ব্যবহারকারী (id)',
        'owner_user_id' => 'মালিক (id)',
        'sms_enabled' => 'SMS বিজ্ঞপ্তি',
        'tag_number' => 'ট্যাগ',
        'category' => 'ধরন',
        'sex' => 'লিঙ্গ',
        'breed' => 'জাত',
        'acquisition_type' => 'কীভাবে এল',
        'purchase_price' => 'ক্রয়মূল্য',
        'purchase_date' => 'কেনার তারিখ',
        'opening_value' => 'প্রারম্ভিক মূল্য',
        'birth_date' => 'জন্ম তারিখ',
        'exit_date' => 'বাদ পড়ার তারিখ',
        'total_amount' => 'মোট টাকা',
        'amount' => 'টাকা',
        'sale_type' => 'বিক্রির ধরন',
        'sold_at' => 'বিক্রির তারিখ',
        'expense_date' => 'খরচের তারিখ',
        'paid_at' => 'পরিশোধের তারিখ',
        'method' => 'মাধ্যম',
        'void_reason' => 'বাতিলের কারণ',
        'report' => 'রিপোর্ট',
        'format' => 'ফরম্যাট',
        'from' => 'শুরু',
        'to' => 'শেষ',
    ],
];
