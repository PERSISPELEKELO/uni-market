<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Student email domains
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of university email domains (for example
    | "student.zut.zm,zut.zm"). Members earn the "Official Student" badge once
    | they have verified their email address. When this list is empty any
    | email provider is accepted, so the badge then only proves that the
    | member controls the email address they registered with.
    |
    */
    'student_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('STUDENT_EMAIL_DOMAINS', ''))
    ))),

];
