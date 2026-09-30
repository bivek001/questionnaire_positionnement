<?php

// ======================================================
// LANGUAGE SYSTEM
// ======================================================

// Start the session only if it has not already been started.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


// ======================================================
// AVAILABLE LANGUAGES
// ======================================================

$availableLanguages = [
    'en',
    'fr'
];


// ======================================================
// CHANGE LANGUAGE
// ======================================================

$requestedInput = $_GET['lang'] ?? $_POST['lang'] ?? null;
if (is_string($requestedInput)) {

    $requestedLanguage =
        strtolower(
            trim($requestedInput)
        );

    if (
        in_array(
            $requestedLanguage,
            $availableLanguages,
            true
        )
    ) {

        $_SESSION['language'] =
            $requestedLanguage;
    }
}


// ======================================================
// CURRENT LANGUAGE
// ======================================================

$currentLanguage =
    $_SESSION['language']
    ?? 'en';


// Safety check in case an invalid value somehow
// exists in the session.
if (
    !in_array(
        $currentLanguage,
        $availableLanguages,
        true
    )
) {

    $currentLanguage = 'en';

    $_SESSION['language'] = 'en';
}


// ======================================================
// LOAD TRANSLATION FILE
// ======================================================

$languageFile =
    __DIR__
    . '/../languages/'
    . $currentLanguage
    . '.php';


if (!file_exists($languageFile)) {

    $currentLanguage = 'en';

    $_SESSION['language'] = 'en';

    $languageFile =
        __DIR__
        . '/../languages/en.php';
}


$translations =
    require $languageFile;


if (!is_array($translations)) {

    $translations = [];
}


// ======================================================
// TRANSLATION FUNCTION
// ======================================================

if (!function_exists('t')) {

    function t(
        string $key,
        array $replacements = []
    ): string {

        global $translations;

        $text =
            $translations[$key]
            ?? $key;


        foreach (
            $replacements as $name => $value
        ) {

            $text =
                str_replace(
                    '{' . $name . '}',
                    (string)$value,
                    $text
                );
        }


        return $text;
    }
}


// ======================================================
// CURRENT LANGUAGE FUNCTION
// ======================================================

if (!function_exists('currentLanguage')) {

    function currentLanguage(): string
    {
        global $currentLanguage;

        return $currentLanguage;
    }
}


// ======================================================
// CHECK LANGUAGE FUNCTION
// ======================================================

if (!function_exists('isLanguage')) {

    function isLanguage(
        string $language
    ): bool {

        return currentLanguage()
            === $language;
    }
}


// ======================================================
// CREATE LANGUAGE SWITCH URL
// ======================================================

if (!function_exists('languageUrl')) {

    function languageUrl(
        string $language
    ): string {

        $allowed = [
            'en',
            'fr'
        ];


        if (
            !in_array(
                $language,
                $allowed,
                true
            )
        ) {

            $language = 'en';
        }


        // Keep existing query parameters such as:
        //
        // ?id=5
        // ?professor_id=2
        // ?attempt_id=10
        //
        // and only change the language parameter.

        $parameters = $_GET;

        $parameters['lang'] =
            $language;


        $query =
            http_build_query(
                $parameters
            );


        $currentPage =
            $_SERVER['PHP_SELF']
            ?? 'index.php';


        return $currentPage
            . ($query !== ''
                ? '?' . $query
                : '');
    }
}


// ======================================================
// HTML LANGUAGE ATTRIBUTE
// ======================================================

if (!function_exists('htmlLanguage')) {

    function htmlLanguage(): string
    {
        return currentLanguage() === 'fr'
            ? 'fr'
            : 'en';
    }
}