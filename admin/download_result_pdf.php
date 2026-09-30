<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/result_pdf.php';


// Get the attempt ID from the URL
$attemptId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$attemptId) {
    die(t('h150_invalid_attempt'));
}


try {

    // Get trainee information
    $stmt = $pdo->prepare(
        "SELECT
            attempts.id,
            trainees.first_name,
            trainees.last_name

         FROM attempts

         INNER JOIN trainees
            ON trainees.id = attempts.trainee_id

         WHERE attempts.id = ?"
    );

    $stmt->execute([$attemptId]);

    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$attempt) {
        throw new Exception(t('p150i_4a818c7a6ae4'));
    }


    // Generate the PDF
    $pdfContent = generateResultPdf($pdo, $attemptId, static fn(string $key, string $fallback): string => t($key));


    // Make the trainee name safe for the filename
    $firstName = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        $attempt['first_name']
    );

    $lastName = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        $attempt['last_name']
    );


    // Example:
    // final_result_Bivek_Chaudhary_attempt_5.pdf
    $filename =
        'final_result_'
        . $firstName
        . '_'
        . $lastName
        . '_attempt_'
        . $attemptId
        . '.pdf';


    // Tell the browser that this is a PDF
    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: attachment; filename="'
        . $filename
        . '"'
    );

    header(
        'Content-Length: '
        . strlen($pdfContent)
    );


    // Send the PDF to the browser
    echo $pdfContent;

    exit;


} catch (Throwable $e) {

    http_response_code(400);

    ?>
    <!DOCTYPE html>
    <html lang="<?= adminH(htmlLanguage()) ?>">

    <head>

        <meta charset="UTF-8">

        <title><?= adminH(t('a150j_bfcd807171a6')) ?></title>

    <link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

    <body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

        <h1><?= adminH(t('p150i_3fc8d3fe52c9')) ?></h1>

        <p>
            <?= htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p>
            <a href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attemptId ?>">
                <?= adminH(t('a150j_d4f7d8f52ce5')) ?>
            </a>
        </p>

    </body>

    </html>

    <?php
}