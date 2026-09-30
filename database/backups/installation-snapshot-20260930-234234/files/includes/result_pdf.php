<?php

use Dompdf\Dompdf;
use Dompdf\Options;


/**
 * Generate the PDF for a completed FINAL questionnaire.
 *
 * @param PDO $pdo
 * @param int $attemptId
 * @param callable|null $translate Optional interface-label translator.
 *
 * @return string
 *
 * @throws Exception
 */
function generateResultPdf(
    PDO $pdo,
    int $attemptId,
    ?callable $translate = null
): string {
    $translate ??= static fn(string $key, string $fallback): string => $fallback;

    // =====================================
    // LOAD COMPOSER
    // =====================================

    $autoloadPath =
        dirname(__DIR__)
        . '/vendor/autoload.php';


    if (!file_exists($autoloadPath)) {

        throw new Exception(
            $translate('p150i_1969a184418b', 'Composer autoload file was not found.')
        );
    }


    require_once $autoloadPath;


    // =====================================
    // GET ATTEMPT INFORMATION
    // =====================================

    $stmt = $pdo->prepare(
        "SELECT
            attempts.*,

            trainees.first_name,
            trainees.last_name,
            trainees.email,

            themes.name AS theme_name

         FROM attempts

         INNER JOIN trainees
            ON attempts.trainee_id = trainees.id

         LEFT JOIN themes
            ON attempts.theme_id = themes.id

         WHERE attempts.id = ?"
    );


    $stmt->execute([
        $attemptId
    ]);


    $attempt =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$attempt) {

        throw new Exception(
            $translate('p150i_4a818c7a6ae4', 'Attempt not found.')
        );
    }


    // =====================================
    // ONLY FINAL ATTEMPTS
    // =====================================

    if (
        $attempt['passation_type']
        !== 'final'
    ) {

        throw new Exception(
            $translate('p150i_a207a08f3a62', 'A result PDF can only be generated for a Final questionnaire.')
        );
    }


    // =====================================
    // ATTEMPT MUST BE COMPLETED
    // =====================================

    if (
        empty($attempt['completed_at'])
    ) {

        throw new Exception(
            $translate('p150i_dfa3e4b7ebcc', 'This questionnaire has not been completed yet.')
        );
    }


    // =====================================
    // CHECK PENDING OPEN QUESTIONS
    // =====================================

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)

         FROM responses

         INNER JOIN questions
            ON questions.id =
               responses.question_id

         WHERE responses.attempt_id = ?

         AND questions.question_type = 'open'

         AND responses.graded_at IS NULL"
    );


    $stmt->execute([
        $attemptId
    ]);


    $pendingOpenAnswers =
        (int)$stmt->fetchColumn();


    if (
        $pendingOpenAnswers > 0
    ) {

        throw new Exception(
            $translate('p150i_ebd0d6d74676', 'The PDF cannot be generated until all open questions have been graded.')
        );
    }


    // =====================================
    // GET RESPONSES
    // =====================================

    $stmt = $pdo->prepare(
        "SELECT
            responses.id AS response_id,
            responses.text_answer,
            responses.awarded_points,
            responses.trainer_comment,
            responses.graded_at,

            questions.id AS question_id,
            questions.question_text,
            questions.question_type,
            questions.points,
            questions.display_order

         FROM responses

         INNER JOIN questions
            ON questions.id =
               responses.question_id

         WHERE responses.attempt_id = ?

         ORDER BY
            questions.display_order ASC,
            responses.id ASC"
    );


    $stmt->execute([
        $attemptId
    ]);


    $responses =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    // =====================================
    // PREPARE CHOICE QUERY
    // =====================================

    $choiceStmt = $pdo->prepare(
        "SELECT
            choices.choice_text,
            choices.is_correct

         FROM response_choices

         INNER JOIN choices
            ON choices.id =
               response_choices.choice_id

         WHERE response_choices.response_id = ?

         ORDER BY
            choices.display_order ASC,
            choices.id ASC"
    );


    // =====================================
    // CALCULATE FINAL RESULT
    // =====================================

    $totalScore =
        (float)$attempt['total_score'];


    $maximumScore =
        (float)$attempt['maximum_score'];


    if (
        $maximumScore > 0
    ) {

        $percentage =
            (
                $totalScore
                / $maximumScore
            ) * 100;

    } else {

        $percentage = 0;
    }


    // =====================================
    // GET POSITIONING LEVEL
    // =====================================

    $level =
        getPositioningLevel($pdo, $percentage, $translate('h150_not_defined', 'Not defined'));


    // =====================================
    // ESCAPE HTML FUNCTION
    // =====================================

    $escape =
        static function ($value): string {
    $translate ??= static fn(string $key, string $fallback): string => $fallback;

            return htmlspecialchars(
                (string)$value,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };


    // =====================================
    // TRAINEE INFORMATION
    // =====================================

    $traineeName = trim(
        $attempt['first_name']
        . ' '
        . $attempt['last_name']
    );


    $themeName =
        !empty($attempt['theme_name'])
        ? $attempt['theme_name']
        : $translate('questionnaire', 'Questionnaire');


    $passationDate =
        !empty($attempt['completed_at'])
        ? $attempt['completed_at']
        : $attempt['started_at'];


    $correctionDate =
        !empty($attempt['corrected_at'])
        ? $attempt['corrected_at']
        : $translate('p150i_5237c96ac1a2', 'Not applicable');


    // =====================================
    // CREATE PDF HTML
    // =====================================

    $html = '
    <!DOCTYPE html>

    <html lang="' . $escape($translate('p150i_pdf_language', 'en')) . '">

    <head>

        <meta charset="UTF-8">

        <style>

            @page {
                margin: 35px;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 11px;
                color: #222;
                line-height: 1.5;
            }

            h1 {
                text-align: center;
                font-size: 24px;
                margin-bottom: 5px;
            }

            .subtitle {
                text-align: center;
                color: #666;
                margin-bottom: 25px;
            }

            h2 {
                font-size: 17px;
                margin-top: 25px;
                border-bottom: 1px solid #bbbbbb;
                padding-bottom: 5px;
            }

            h3 {
                font-size: 13px;
                margin-bottom: 6px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            th,
            td {
                border: 1px solid #cccccc;
                padding: 7px;
                text-align: left;
                vertical-align: top;
            }

            th {
                width: 32%;
                background: #f2f2f2;
            }

            .result-box {
                border: 1px solid #999999;
                padding: 12px;
                margin-top: 15px;
                margin-bottom: 20px;
            }

            .question {
                margin-bottom: 18px;
                padding-bottom: 15px;
                border-bottom: 1px solid #dddddd;
                page-break-inside: avoid;
            }

            .answer {
                background: #f7f7f7;
                padding: 8px;
                margin-top: 6px;
            }

            .comment {
                margin-top: 7px;
                padding: 7px;
                border-left: 3px solid #999999;
            }

            .footer {
                margin-top: 30px;
                font-size: 9px;
                color: #777777;
                text-align: center;
            }

        </style>

    </head>

    <body>


        <h1>
            ' . $escape($translate('p150i_3f4f01ae7145', 'Final Questionnaire Result')) . '
        </h1>


        <div class="subtitle">'
            . $escape($themeName) .
        '</div>


        <h2>
            ' . $escape($translate('p150i_15c54949c30b', 'Trainee Information')) . '
        </h2>


        <table>


            <tr>

                <th>
                    ' . $escape($translate('name', 'Name')) . '
                </th>

                <td>'
                    . $escape(
                        $traineeName
                    ) .
                '</td>

            </tr>


            <tr>

                <th>
                    ' . $escape($translate('email', 'Email')) . '
                </th>

                <td>'
                    . $escape(
                        $attempt['email']
                    ) .
                '</td>

            </tr>


            <tr>

                <th>
                    ' . $escape($translate('questionnaire', 'Questionnaire')) . '
                </th>

                <td>'
                    . $escape(
                        $themeName
                    ) .
                '</td>

            </tr>


            <tr>

                <th>
                    ' . $escape($translate('passation_type', 'Passation Type')) . '
                </th>

                <td>
                    ' . $escape($translate('final', 'Final')) . '
                </td>

            </tr>


            <tr>

                <th>
                    ' . $escape($translate('p150i_66df757e0889', 'Date de passation')) . '
                </th>

                <td>'
                    . $escape(
                        $passationDate
                    ) .
                '</td>

            </tr>


            <tr>

                <th>
                    ' . $escape($translate('p150i_4ab141c17cd9', 'Date de correction')) . '
                </th>

                <td>'
                    . $escape(
                        $correctionDate
                    ) .
                '</td>

            </tr>


        </table>


        <h2>
            ' . $escape($translate('p150i_f839ec37176b', 'Final Result')) . '
        </h2>


        <div class="result-box">


            <strong>
                ' . $escape($translate('h150_score_label', 'Score:')) . '
            </strong>

            '
            . $escape(
                $totalScore
            )
            . ' / '
            . $escape(
                $maximumScore
            )
            . '


            <br>


            <strong>
                ' . $escape($translate('p150i_57847f853a13', 'Percentage:')) . '
            </strong>

            '
            . number_format(
                $percentage,
                1
            )
            . '%


            <br>


            <strong>
                ' . $escape($translate('p150i_15b6d09d829e', 'Positioning Level:')) . '
            </strong>

            '
            . $escape(
                $level
            )
            . '


        </div>


        <h2>
            ' . $escape($translate('p150i_a4fac70de8c7', 'Question Details')) . '
        </h2>
    ';


    // =====================================
    // ADD QUESTIONS TO PDF
    // =====================================

    foreach (
        $responses as
        $index => $response
    ) {

        $questionNumber =
            $index + 1;


        $html .= '

            <div class="question">


                <h3>
                    ' . $escape($translate('question', 'Question')) . ' '
                    . $questionNumber .
                '</h3>


                <div>'
                    . nl2br(
                        $escape(
                            $response[
                                'question_text'
                            ]
                        )
                    ) .
                '</div>
        ';


        // =================================
        // OPEN QUESTION
        // =================================

        if (
            $response['question_type']
            === 'open'
        ) {

            $answer =
                $response['text_answer']
                ?? '';


            $html .= '

                <p>
                    <strong>
                        ' . $escape($translate('p150i_a1803d4bcabd', 'Trainee Answer:')) . '
                    </strong>
                </p>


                <div class="answer">'
                    . nl2br(
                        $escape(
                            $answer
                        )
                    ) .
                '</div>
            ';


            // =================================
            // TRAINER COMMENT
            // =================================

            if (
                !empty(
                    $response[
                        'trainer_comment'
                    ]
                )
            ) {

                $html .= '

                    <div class="comment">


                        <strong>
                            ' . $escape($translate('p150i_8fe035bcd5c5', 'Trainer Comment:')) . '
                        </strong>


                        <br>


                        '
                        . nl2br(
                            $escape(
                                $response[
                                    'trainer_comment'
                                ]
                            )
                        )
                        . '


                    </div>
                ';
            }


        } else {

            // =================================
            // CHOICE QUESTION
            // =================================

            $choiceStmt->execute([
                $response['response_id']
            ]);


            $selectedChoices =
                $choiceStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $html .= '

                <p>

                    <strong>
                        ' . $escape($translate('p150i_565d372b4dc2', 'Selected Answer(s):')) . '
                    </strong>

                </p>
            ';


            // =================================
            // NO ANSWER
            // =================================

            if (
                empty(
                    $selectedChoices
                )
            ) {

                $html .= '

                    <div class="answer">

                        ' . $escape($translate('h150_no_answer_selected', 'No answer selected.')) . '

                    </div>
                ';


            } else {

                // =================================
                // SELECTED CHOICES
                // =================================

                $html .= '<ul>';


                foreach (
                    $selectedChoices
                    as $choice
                ) {

                    $choiceLabel =
                        $escape(
                            $choice[
                                'choice_text'
                            ]
                        );


                    if (
                        (int)$choice[
                            'is_correct'
                        ] === 1
                    ) {

                        $choiceLabel .=
                            $translate('p150i_745276c9957a', ' - Correct');

                    } else {

                        $choiceLabel .=
                            $translate('p150i_66101faadbb5', ' - Incorrect');
                    }


                    $html .= '

                        <li>'
                            . $choiceLabel .
                        '</li>
                    ';
                }


                $html .= '</ul>';
            }
        }


        // =================================
        // QUESTION SCORE
        // =================================

        $html .= '

            <p>

                <strong>
                    ' . $escape($translate('h150_score_label', 'Score:')) . '
                </strong>

                '
                . $escape(
                    $response[
                        'awarded_points'
                    ]
                )
                . ' / '
                . $escape(
                    $response[
                        'points'
                    ]
                )
                . '

            </p>


        </div>
        ';
    }


    // =====================================
    // PDF FOOTER
    // =====================================

    $html .= '

        <div class="footer">

            ' . $escape($translate('p150i_5467e43e6189', 'Generated automatically by the Training Assessment Portal.')) . '

        </div>


    </body>

    </html>
    ';


    // =====================================
    // DOMPDF OPTIONS
    // =====================================

    $options =
        new Options();


    $options->set(
        'defaultFont',
        'DejaVu Sans'
    );


    $options->set(
        'isRemoteEnabled',
        false
    );


    // =====================================
    // GENERATE PDF
    // =====================================

    $dompdf =
        new Dompdf(
            $options
        );


    $dompdf->loadHtml(
        $html,
        'UTF-8'
    );


    $dompdf->setPaper(
        'A4',
        'portrait'
    );


    $dompdf->render();


    // =====================================
    // RETURN PDF CONTENT
    // =====================================

    return $dompdf->output();
}
