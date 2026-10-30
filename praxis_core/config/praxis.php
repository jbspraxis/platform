<?php

/*
| JBS Praxis's own rules, switched by the owner (the site API's limits are in config/site_api.php).
*/

return [

    /*
    | Careers: what the privacy policy on jbspraxis.com promises (section "How long we keep it"). A CV is
    | deleted, and its application anonymised, this many months after it was received (careers:purge-cvs).
    | Change it only together with the policy (praxis_site lang/{en,fr}/legal.php).
    */
    'cv_retention_months' => 12,

    // careers:purge-cvs runs every night only when this is on (CV_RETENTION_PURGE=true): the owner's switch.
    // Run "php artisan careers:purge-cvs --dry-run" first to see what it would remove.
    'cv_retention_purge' => (bool) env('CV_RETENTION_PURGE', false),

];
