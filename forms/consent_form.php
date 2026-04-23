<?php
// Save consent form submission as individual HTML file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['participant_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars(trim($_POST['consent_date'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($name) || empty($date)) {
        $error = 'Please fill in your name and date.';
    } else {
        // Check all consent boxes were ticked
        $all_consented = true;
        for ($i = 0; $i < 6; $i++) {
            if (!isset($_POST["consent_$i"])) {
                $all_consented = false;
                break;
            }
        }
        if (!$all_consented) {
            $error = 'Please tick all consent statements.';
        } else {
            // Build the saved consent HTML
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
            $filename = 'consent_signed_' . $safe_filename . '.html';

            $statements = array(
                'I confirm that I have read and understood the information above.',
                'I understand that my participation is voluntary and I can withdraw at any time without giving a reason.',
                'I agree that my anonymised responses may be used in the project report.',
                'I understand that no personally identifiable information will be published.',
                'I consent to being photographed from behind (no face visible) during the testing session.',
                'I agree to take part in this usability evaluation.',
            );
            $checks = '';
            foreach ($statements as $s) {
                $checks .= '<div class="checkbox-row"><div class="checkbox">&#10003;</div><span>' . $s . '</span></div>' . "
";
            }

            $css = <<<'CSSBLOCK'

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    color: #34516C;
    background: #fff;
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 50px;
    line-height: 1.5;
}
@media print { body { padding: 20px 30px; } }
h1 { font-size: 22px; color: #BE1622; text-align: center; margin-bottom: 4px; }
h2 { font-size: 17px; color: #2E74B5; text-align: center; margin-bottom: 20px; }
h3 {
    font-size: 15px; color: #2E74B5;
    margin: 20px 0 8px 0;
    border-bottom: 1px solid #D9E2F3; padding-bottom: 4px;
}
.info-grid {
    display: grid; grid-template-columns: 140px 1fr;
    gap: 4px 12px; margin: 10px 0 16px 0;
}
.info-grid .label { font-weight: 600; font-size: 13px; }
.info-grid .value { font-size: 13px; }
p { margin: 8px 0; font-size: 13px; }
.task-list { margin: 8px 0 8px 20px; }
.task-list li { margin: 4px 0; font-size: 13px; }
hr.divider { border: none; border-top: 1px solid #D9E2F3; margin: 16px 0; }
.checkbox-row {
    display: flex; align-items: flex-start; gap: 10px;
    margin: 8px 0; font-size: 13px;
}
.checkbox {
    width: 18px; height: 18px; border: 2px solid #2E74B5;
    border-radius: 3px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px; font-weight: 700; color: #2E74B5; font-size: 14px;
}
.sig-field { margin: 14px 0; font-size: 13px; }
.sig-field .line {
    border-bottom: 1px solid #999; min-width: 300px;
    display: inline-block; margin-left: 8px;
    padding-bottom: 2px; font-style: italic; color: #2E74B5;
}
.footer-note {
    margin-top: 30px; text-align: center; font-size: 11px; color: #999;
}
input[type="text"], select {
    padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; font-family: inherit; color: #34516C;
}
input[type="text"] { width: 280px; }
input[type="text"]:focus, select:focus {
    outline: none; border-color: #2E74B5;
    box-shadow: 0 0 0 2px rgba(46,116,181,0.15);
}
label.cb-label {
    display: flex; align-items: flex-start; gap: 10px;
    margin: 8px 0; font-size: 13px; cursor: pointer;
}
label.cb-label input[type="checkbox"] {
    width: 18px; height: 18px; margin-top: 2px; accent-color: #2E74B5;
}
.btn {
    display: inline-block; padding: 10px 32px;
    background: #2E74B5; color: #fff; border: none; border-radius: 5px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    margin-top: 16px;
}
.btn:hover { background: #245d94; }
.scale-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
.scale-table th, .scale-table td {
    padding: 8px 6px; font-size: 13px; border-bottom: 1px solid #E8EDF3;
}
.scale-table th {
    background: #D9E2F3; color: #34516C; font-size: 12px; text-align: center;
}
.scale-table th:first-child { text-align: left; width: 55%; }
.scale-table td:first-child { text-align: left; }
.scale-table td { text-align: center; }
.radio-cell {
    width: 36px; height: 36px; border: 2px solid #2E74B5; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: #ccc;
}
.radio-cell.selected { background: #2E74B5; color: #fff; }
.score-box {
    margin: 20px 0; padding: 12px 20px;
    background: #F0F4FA; border-left: 4px solid #2E74B5; font-size: 14px;
}
.score-box strong { color: #2E74B5; }
.comment-box {
    margin: 12px 0; padding: 12px 16px;
    border: 1px solid #D9E2F3; border-radius: 6px;
    min-height: 60px; font-size: 13px;
    font-style: italic; color: #555; background: #FAFBFD;
}
textarea {
    width: 100%; min-height: 80px; padding: 10px 12px;
    border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; font-family: inherit; color: #34516C; resize: vertical;
}
textarea:focus {
    outline: none; border-color: #2E74B5;
    box-shadow: 0 0 0 2px rgba(46,116,181,0.15);
}
.error { color: #BE1622; font-size: 12px; margin: 4px 0; }
.success-box {
    margin: 30px 0; padding: 20px; background: #e8f5e9;
    border-left: 4px solid #4caf50; border-radius: 4px;
    text-align: center; font-size: 15px; color: #2e7d32;
}

CSSBLOCK;

            $project_info = <<<'INFOBLOCK'
<div class="info-grid">
    <span class="label">Project Title:</span>
    <span class="value">Library Management System with Personalised Recommendations and Reservation Tracking</span>
    <span class="label">Researcher:</span>
    <span class="value">Akkala Dinesh Kumar Reddy</span>
    <span class="label">Supervisor:</span>
    <span class="value">Dr. Alina Latipova</span>
    <span class="label">Module:</span>
    <span class="value">CO4804 MSc Computing Project</span>
    <span class="label">Institution:</span>
    <span class="value">University of Lancashire</span>
</div>
INFOBLOCK;

            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Consent Form - $name</title>
<style>
$css
</style>
</head>
<body>
<h1>Participant Consent Form</h1>
<h2>Usability Evaluation of UniLibrary System</h2>
<h3>Project Details</h3>
$project_info
<h3>Purpose of the Study</h3>
<p>You are being invited to take part in a usability evaluation of a web based library management system developed as part of an MSc Computing project. The purpose of this study is to assess how easy the system is to use and to gather feedback that will help identify areas for improvement.</p>
<h3>What Your Participation Involves</h3>
<ul class="task-list">
    <li>You will be asked to complete a set of tasks using the UniLibrary system.</li>
    <li>After completing the tasks, you will fill in a short questionnaire (System Usability Scale).</li>
    <li>A photograph may be taken from behind (no face visible) for documentation purposes.</li>
    <li>The session should take approximately 15 to 20 minutes.</li>
</ul>
<h3>Consent Statements</h3>
<p>All statements agreed:</p>
<div style="margin: 12px 0 12px 8px;">
$checks
</div>
<hr class="divider">
<h3>Participant Details</h3>
<div class="sig-field"><strong>Name:</strong> <span class="line">$name</span></div>
<div class="sig-field"><strong>Date:</strong> <span class="line">$date</span></div>
<h3>Researcher Details</h3>
<div class="sig-field"><strong>Name:</strong> <span class="line">Akkala Dinesh Kumar Reddy</span></div>
<div class="sig-field"><strong>Date:</strong> <span class="line">$date</span></div>
<div class="footer-note">Form signed on $date</div>
</body>
</html>
HTML;

            file_put_contents(__DIR__ . '/' . $filename, $html);
            $success = "Consent form saved as <strong>$filename</strong>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Participant Consent Form</title>
<style>

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    color: #34516C;
    background: #fff;
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 50px;
    line-height: 1.5;
}
@media print { body { padding: 20px 30px; } }
h1 { font-size: 22px; color: #BE1622; text-align: center; margin-bottom: 4px; }
h2 { font-size: 17px; color: #2E74B5; text-align: center; margin-bottom: 20px; }
h3 {
    font-size: 15px; color: #2E74B5;
    margin: 20px 0 8px 0;
    border-bottom: 1px solid #D9E2F3; padding-bottom: 4px;
}
.info-grid {
    display: grid; grid-template-columns: 140px 1fr;
    gap: 4px 12px; margin: 10px 0 16px 0;
}
.info-grid .label { font-weight: 600; font-size: 13px; }
.info-grid .value { font-size: 13px; }
p { margin: 8px 0; font-size: 13px; }
.task-list { margin: 8px 0 8px 20px; }
.task-list li { margin: 4px 0; font-size: 13px; }
hr.divider { border: none; border-top: 1px solid #D9E2F3; margin: 16px 0; }
.checkbox-row {
    display: flex; align-items: flex-start; gap: 10px;
    margin: 8px 0; font-size: 13px;
}
.checkbox {
    width: 18px; height: 18px; border: 2px solid #2E74B5;
    border-radius: 3px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px; font-weight: 700; color: #2E74B5; font-size: 14px;
}
.sig-field { margin: 14px 0; font-size: 13px; }
.sig-field .line {
    border-bottom: 1px solid #999; min-width: 300px;
    display: inline-block; margin-left: 8px;
    padding-bottom: 2px; font-style: italic; color: #2E74B5;
}
.footer-note {
    margin-top: 30px; text-align: center; font-size: 11px; color: #999;
}
input[type="text"], select {
    padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; font-family: inherit; color: #34516C;
}
input[type="text"] { width: 280px; }
input[type="text"]:focus, select:focus {
    outline: none; border-color: #2E74B5;
    box-shadow: 0 0 0 2px rgba(46,116,181,0.15);
}
label.cb-label {
    display: flex; align-items: flex-start; gap: 10px;
    margin: 8px 0; font-size: 13px; cursor: pointer;
}
label.cb-label input[type="checkbox"] {
    width: 18px; height: 18px; margin-top: 2px; accent-color: #2E74B5;
}
.btn {
    display: inline-block; padding: 10px 32px;
    background: #2E74B5; color: #fff; border: none; border-radius: 5px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    margin-top: 16px;
}
.btn:hover { background: #245d94; }
.scale-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
.scale-table th, .scale-table td {
    padding: 8px 6px; font-size: 13px; border-bottom: 1px solid #E8EDF3;
}
.scale-table th {
    background: #D9E2F3; color: #34516C; font-size: 12px; text-align: center;
}
.scale-table th:first-child { text-align: left; width: 55%; }
.scale-table td:first-child { text-align: left; }
.scale-table td { text-align: center; }
.radio-cell {
    width: 36px; height: 36px; border: 2px solid #2E74B5; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: #ccc;
}
.radio-cell.selected { background: #2E74B5; color: #fff; }
.score-box {
    margin: 20px 0; padding: 12px 20px;
    background: #F0F4FA; border-left: 4px solid #2E74B5; font-size: 14px;
}
.score-box strong { color: #2E74B5; }
.comment-box {
    margin: 12px 0; padding: 12px 16px;
    border: 1px solid #D9E2F3; border-radius: 6px;
    min-height: 60px; font-size: 13px;
    font-style: italic; color: #555; background: #FAFBFD;
}
textarea {
    width: 100%; min-height: 80px; padding: 10px 12px;
    border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; font-family: inherit; color: #34516C; resize: vertical;
}
textarea:focus {
    outline: none; border-color: #2E74B5;
    box-shadow: 0 0 0 2px rgba(46,116,181,0.15);
}
.error { color: #BE1622; font-size: 12px; margin: 4px 0; }
.success-box {
    margin: 30px 0; padding: 20px; background: #e8f5e9;
    border-left: 4px solid #4caf50; border-radius: 4px;
    text-align: center; font-size: 15px; color: #2e7d32;
}

</style>
</head>
<body>

<h1>Participant Consent Form</h1>
<h2>Usability Evaluation of UniLibrary System</h2>

<?php if (!empty($success)): ?>
    <div class="success-box"><?php echo $success; ?></div>
    <p style="text-align:center; margin-top: 12px;">
        <a href="sus_survey.php" class="btn" style="text-decoration:none;">Proceed to SUS Survey</a>
    </p>
<?php else: ?>

<?php if (!empty($error)): ?>
    <div class="error" style="text-align:center; font-size:14px; margin-bottom:12px;"><?php echo $error; ?></div>
<?php endif; ?>

<h3>Project Details</h3>
<div class="info-grid">
    <span class="label">Project Title:</span>
    <span class="value">Library Management System with Personalised Recommendations and Reservation Tracking</span>
    <span class="label">Researcher:</span>
    <span class="value">Akkala Dinesh Kumar Reddy</span>
    <span class="label">Supervisor:</span>
    <span class="value">Dr. Alina Latipova</span>
    <span class="label">Module:</span>
    <span class="value">CO4804 MSc Computing Project</span>
    <span class="label">Institution:</span>
    <span class="value">University of Lancashire</span>
</div>

<h3>Purpose of the Study</h3>
<p>
    You are being invited to take part in a usability evaluation of a web based
    library management system developed as part of an MSc Computing project. The
    purpose of this study is to assess how easy the system is to use and to gather
    feedback that will help identify areas for improvement.
</p>

<h3>What Your Participation Involves</h3>
<ul class="task-list">
    <li>You will be asked to complete a set of tasks using the UniLibrary system.</li>
    <li>After completing the tasks, you will fill in a short questionnaire (System Usability Scale).</li>
    <li>A photograph may be taken from behind (no face visible) for documentation purposes.</li>
    <li>The session should take approximately 15 to 20 minutes.</li>
</ul>

<form method="POST" action="">
<h3>Consent Statements</h3>
<p>Please read and tick each box to confirm your agreement:</p>
<div style="margin: 12px 0 12px 8px;">
    <label class="cb-label">
        <input type="checkbox" name="consent_0" value="yes" required>
        <span>I confirm that I have read and understood the information above.</span>
    </label>
    <label class="cb-label">
        <input type="checkbox" name="consent_1" value="yes" required>
        <span>I understand that my participation is voluntary and I can withdraw at any time without giving a reason.</span>
    </label>
    <label class="cb-label">
        <input type="checkbox" name="consent_2" value="yes" required>
        <span>I agree that my anonymised responses may be used in the project report.</span>
    </label>
    <label class="cb-label">
        <input type="checkbox" name="consent_3" value="yes" required>
        <span>I understand that no personally identifiable information will be published.</span>
    </label>
    <label class="cb-label">
        <input type="checkbox" name="consent_4" value="yes" required>
        <span>I consent to being photographed from behind (no face visible) during the testing session.</span>
    </label>
    <label class="cb-label">
        <input type="checkbox" name="consent_5" value="yes" required>
        <span>I agree to take part in this usability evaluation.</span>
    </label>

</div>

<hr class="divider">

<h3>Participant Details</h3>
<div class="sig-field">
    <strong>Name:</strong>
    <input type="text" name="participant_name" placeholder="Your full name" required>
</div>
<div class="sig-field">
    <strong>Date:</strong>
    <input type="text" name="consent_date" value="<?php echo date('d F Y'); ?>" required>
</div>

<div style="text-align: center; margin-top: 20px;">
    <button type="submit" class="btn">Sign and Submit Consent</button>
</div>
</form>

<?php endif; ?>

</body>
</html>