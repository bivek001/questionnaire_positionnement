<?php
// Configure these environment variables in Apache, then restart Apache.
// URL must be the trusted project URL, without a trailing slash (never use HTTP_HOST).
return [
    'base_url' => rtrim(getenv('QUESTIONNAIRE_BASE_URL') ?: '', '/'),
    'from' => getenv('QUESTIONNAIRE_MAIL_FROM') ?: '',
    // Explicit opt-in AND loopback client are both required for visible test links.
    'local_testing' => false,
];
