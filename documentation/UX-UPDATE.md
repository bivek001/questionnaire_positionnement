# Questionnaire positioning UX update

Based on the latest local project at `C:/xampp/htdocs/questionnaire_positionnement`, inspected on 5 October 2026. Step 152 and server upgrades remain on hold. The running project and its database were not changed.

## What changed

- Trainee: one question at a time with its reading context, course hierarchy and explicit source codes; answered/total and percentage; readable choices and medium textarea; paragraph and question media; Previous/Next, question navigation, unanswered warning and final confirmation. Without JavaScript, the complete form remains visible.
- Save: authenticated CSRF-protected session drafts, scoped to trainee and assignment/course. Saved values are restricted to current questions and their choices. Language switching preserves answers. Successful submission clears that draft. Saving does not create an attempt. PHP session expiry can remove a draft; this is not permanent storage or cross-device resume.
- Course immediately follows Dashboard. New Admin/Professor Course workspaces show Theme > Chapter > Lesson > Topic > Paragraph > Question, source placement guidance, staff-only source threshold excerpts, explicit competency associations and a trainee preview without corrections. Existing structure/question editors retain their authorization and validation.
- Shared exercises can have multiple source competency codes without duplicating question points. Mapping changes are refused once responses exist, protecting history.
- Course result lists show attempts, scoped score, level, automatic open-answer total, human awarded open-answer total, pending status and eligible Print/PDF. Professor results require course permission, not trainee assignment. Chapter-only scopes retain restricted responses and scores; complete PDF still requires whole-theme authorization.
- New open-answer suggestions use 0/25/50/75/100%: no matches = 0; coverage up to 25% = 25; up to 50% = 50; incomplete coverage above 50% = 75; every configured criterion = 100. Old snapshots and human grades are not rewritten. Human review and updates remain authoritative. Keyword coverage is a heuristic, not semantic assessment of writing quality.
- Rich paragraphs are sanitized for author preview and trainee reading. Double-escaped legacy fragments decode before sanitization. Tables retain safe spans/headers; allowed media survives; active tags, unsafe URLs and event attributes are removed. Role-relative image paths work in author previews.
- Trainee page styling moved into shared CSS. Existing shared accessible password controls, keyboard focus and bilingual UI are retained; notification control uses a bell.

## Source fidelity and remaining content work

The authoritative document is `D05-3-PP - Positionnement Initial correction .docx`, found under Downloads/1-Initial/2-Correction Initial. French wording in the source catalogue is preserved; it is not translated by the EN interface. All 10 introduction domains are retained. Domains 1, 2, 3, 4, 6, 8, 9 belong to the transversal diagnostic; 5, 7, 10 appear in the professional diagnostic.

The inspected local database has no themes, questions, paragraphs or uploaded course images. Consequently this package is a UX implementation and source modelling framework, not a fully imported Word questionnaire. It does not invent missing course content or populate the database automatically. Enter the source exercises using the Course workspace, choosing existing supported types (single choice, multiple choice, open) only where appropriate. Ranking, inventories, tables, observation and qualitative tasks must be reviewed before choosing their digital answer format. Oral evaluation refers to annex 1; do not fabricate an unavailable annex.

The private PHP catalogue retains source competency headings and directly associated threshold excerpts. It is an author reference, not a complete transcription of exercise text, correction answers, tables or visuals. Verify every association against the original document. Shared questions such as the email exercise may assess several criteria; choose all applicable codes. Corrections and key criteria belong in staff grading fields, never in Paragraph, choices visible as marked correct, or exercise images.

Source anomalies are deliberately unresolved: 1a.4 lists 0–3/5 and 5/5, leaving 4/5 unspecified; 2c.5 lists mastery at 16/17; 2a.3 has differing ranges; 1d.2 and 1d.3 use qualitative criteria. The 1a.2 answer names Martine Durand while the excerpt credits Marine Durand. Do not silently rewrite these. Staff competency diagnostics classify only an unambiguous numeric pair whose source denominator matches the mapped exercise maximum, with full course access, no shared scoring and no pending open reviews. Qualitative criteria, conflicting ranges, score gaps and other cases explicitly require human source review. The global course level remains the existing configurable level system, separate from these competency thresholds.

For visual exercises (plans, schedules, graphs, organigrams, posture/recycling images), supply the exact corresponding original visual and any trainee-safe table in Paragraph or Question media. Some correction images contain answers; do not expose an annotated correction image to trainees. Missing visuals remain missing rather than being replaced with invented drawings.

## Apply to a staging copy

1. Back up the project, uploads and database. Keep your current database connection and uploaded media. This package contains no production database export or account dump.
2. Apply `database/migrations/20261005_ux.sql` once to the existing database. It only creates the question-to-competency mapping table. Existing migrations are retained; do not rerun older migrations blindly. Until the new migration is applied, questionnaires still work without mappings and author mapping saves show a clear error.
3. Test the role checklist before replacing the running project. Preserve web-server restrictions on private folders. Do not publish the private project archive.
4. Populate and review the source questionnaire separately before treating it as ready for trainee use. Author preview is not approval of imported source content.

No PHP 7.3 deployment compatibility claim is made. Validation used local PHP 8.2.12. The existing application/vendor set contains newer PHP requirements, including arrow functions and a `never` return type. The touched substring matching no longer needs `str_contains`, but that is not a full compatibility audit. No server upgrade, dependency installation or deployment was performed.

## Verification completed

Local isolated database and test sessions: helper tests for legacy HTML, script/attribute filtering, table spans, safe images, all five automatic tiers, incomplete criteria capped at 75%, invalid draft IDs/choices and ten source domains. HTTP checks passed for trainee rendering, correction secrecy, save/reload, EN/FR answer preservation, missing CSRF rejection, Admin preview, Professor chapter scope and course ID tampering, course attempts without trainee assignment, staff grading labels, unrelated mapping rejection, human override and restricted PDF rejection. PHP syntax and JavaScript syntax checks passed. Browser checks at 1280px and 390px confirmed responsive reading/navigation and retained answers; progress changed to 2/3 (67%). The preview contains clearly labelled synthetic fixture content, not an imported final test. Full eligible PDF rendering, real Word content import, production email delivery and PHP 7.3 deployment remain unverified.

## Manual test checklist

### Trainee

- Test EN and FR; authored French content must remain unchanged.
- Confirm title, diagnostic, domain and all mapped competency codes. Read paragraph/context before each associated question.
- Answer open, single and multiple questions. Check answered/total and percentage, Previous/Next, question dropdown and desktop navigation.
- Save, reload and switch language; confirm answers survive. Check different assignments/users cannot load another draft. Verify session-expiry expectations.
- Check formatted paragraphs, double-escaped legacy content, tables and exact source visuals on phone/tablet/desktop. Images should open at full size.
- Confirm correction/model answers, marked-correct choices, keywords and grading criteria are absent before submission, including HTML/page source.
- Submit with unanswered questions: confirm warning, cancel and navigate back, then finish intentionally. Verify one completed attempt, cleared draft and provisional result for open answers.
- Use keyboard, focus controls and password show/hide; check error/success messages and notification bell.

### Admin

- Course appears immediately after Dashboard; Trainee Management and Trainee Results remain separate.
- Add/edit Theme, Chapter, Lesson, Topic and Paragraph; add Question with choices/points/media/correction/key criteria. Confirm a complete source correction page is never pasted into trainee content.
- Preview sanitized source paragraph, safe table/images and question controls. Map one or several source codes; verify attempted mappings cannot change.
- Review source thresholds and qualitative criteria against Word; check 16/17, gaps and ambiguous ranges never become a guessed pass percentage.
- Confirm result attempts, score, course level, automatic and human scores, review status and eligible Print/PDF. Override a suggestion, update it again and check the immutable initial suggestion remains visible.
- Verify CSRF rejection, ID tampering, upload type validation and protected private files.

### Professor

- Authorized courses and chapters only, including author preview and question mapping. Direct unrelated course/question IDs must return 403.
- View/grade an authorized course attempt even without trainee or questionnaire assignment. Unrelated responses and scores must remain absent.
- Review criterion matches and automatic tier; award/update a human score and verify attempt totals/status.
- Chapter-only professor: full-attempt PDF must be unavailable and direct download denied. Whole-theme professor: eligible completed, corrected final attempt offers Print/PDF; check actual PDF content and print layout.

## Archive contents

The application archive includes all application source and documentation, but excludes the unchanged `vendor/` libraries. Retain your existing `vendor/`, database configuration and uploads when applying it. The smaller changed-files archive contains only additions and edits. The prepared `questionnaire_positionnement` folder also retains the copied libraries. No database export, account data or test fixture is included.
