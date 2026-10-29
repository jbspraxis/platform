<?php

// CopqReportMail: the cost of poor quality report, from the calculator on jbspraxis.com or re-sent from HQ.
// Write out "cost of poor quality" before any use of COPQ.
return [
    'subject' => 'Your report: the cost of poor quality at :company',
    'subject_plain' => 'Your report: the cost of poor quality',
    'preheader' => 'Your estimate, and a link to the full report.',
    'headline' => 'Your cost of poor quality report is ready',
    'intro' => 'We estimate that poor quality costs :company about :amount a year.',
    'intro_plain' => 'We estimate that poor quality costs your organisation about :amount a year.',
    'explain' => 'The cost of poor quality is what an organisation loses to manual checking, to errors fixed internally, and to errors that reach its customers or the people it serves. Your full report shows how we reached this estimate and where the cost comes from.',
    'label_amount' => 'Estimated cost per year',
    'label_date' => 'Prepared on',
    'report_language' => '', // the report page is in English
    'button' => 'View your report',
    'share' => 'Anyone with this link can open the report, so share it only with the people in your organisation who need it.',
    'estimate_note' => 'These figures are estimates based on the details provided and standard assumptions for your sector. Reply to this email if you’d like to go through them with us in a free audit and demo.',
    'reason_hq' => 'You are receiving this because JBS Praxis prepared this report for your organisation.',
];
