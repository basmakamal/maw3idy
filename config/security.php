<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | Report-only lets a new or tightened policy be observed in the browser
    | console before it starts blocking anything. Enforcing is the default;
    | set CSP_REPORT_ONLY=true while changing the policy.
    |
    */

    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),

];
