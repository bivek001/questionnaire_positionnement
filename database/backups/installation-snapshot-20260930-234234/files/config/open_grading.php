<?php
// No external calls or credentials are enabled by default.
return [
    'mode' => 'deterministic',
    // Optional SERVER-SIDE callable supplied by the deployment owner after explicitly
    // configuring a provider, credentials, privacy policy and request timeout.
    // Receives question/paragraph/context/reference/rubric/answer/max_points snapshots.
    // Returns ['points' => number, 'explanation' => string]. Suggestions only.
    'provider' => null,
];
