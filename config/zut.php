<?php

// TODO: replace this placeholder list with the official Zambia University
// College of Technology school/faculty list before this goes live. Every
// "school" field on a user is validated against these keys, so changing the
// list later is just an edit here - no code elsewhere needs to change.
return [
    'schools' => [
        'ict' => 'School of ICT',
        'business' => 'School of Business',
        'engineering' => 'School of Engineering',
        'health' => 'School of Health Sciences',
        'education' => 'School of Education',
        'other' => 'Other',
    ],

    // The calendar month a new academic year begins in - used only to turn
    // a student's intake_year into a current year-of-study. Assumed
    // September; change this if the real academic calendar starts earlier
    // or later (e.g. January).
    'academic_year_start_month' => 9,
];
