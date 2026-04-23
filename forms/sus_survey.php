<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = htmlspecialchars(trim($_POST['participant_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pdate = htmlspecialchars(trim($_POST['survey_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $comment = htmlspecialchars(trim($_POST['comments'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($pid) || empty($pdate)) {
        $error = 'Please fill in Participant ID and Date.';
    } else {
        $scores = array();
        $valid = true;
        for ($i = 1; $i <= 10; $i++) {
            if (!isset($_POST["q$i"])) {
                $valid = false;
                break;
            }
            $scores[] = intval($_POST["q$i"]);
        }

        if (!$valid) {
            $error = 'Please answer all 10 questions.';
        } else {
            // Calculate SUS score
            $total = 0;
            for ($i = 0; $i < 10; $i++) {
                if (($i + 1) % 2 === 1) {
                    $total += ($scores[$i] - 1);
                } else {
                    $total += (5 - $scores[$i]);
                }
            }
            $sus_score = $total * 2.5;

            if ($sus_score >= 85) $rating = 'Excellent';
            elseif ($sus_score >= 73) $rating = 'Good';
            elseif ($sus_score >= 52) $rating = 'OK';
            else $rating = 'Poor';

            // Append to CSV
            $csv_dir = __DIR__ . '/responses';
            if (!is_dir($csv_dir)) { mkdir($csv_dir, 0755, true); }
            $csv_path = $csv_dir . '/sus_responses.csv';
            $file_exists = file_exists($csv_path) && filesize($csv_path) > 0;
            $fp = fopen($csv_path, 'a');
            if (!$file_exists) {
                fputcsv($fp, array('Participant ID', 'Date',
                    'Q1','Q2','Q3','Q4','Q5','Q6','Q7','Q8','Q9','Q10',
                    'SUS Score', 'Rating', 'Comments'));
            }
            $row = array_merge(array($pid, $pdate), $scores,
                               array(number_format($sus_score, 1), $rating, $comment));
            fputcsv($fp, $row);
            fclose($fp);

            $success = "Response recorded. SUS Score: <strong>" .
                       number_format($sus_score, 1) . "</strong> (" . $rating . ")";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SUS Questionnaire</title>
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

.scale-table input[type="radio"] { accent-color: #2E74B5; width: 18px; height: 18px; }
.scale-table label { cursor: pointer; display: flex; align-items: center; gap: 4px; justify-content: center; }
</style>
</head>
<body>

<h1>System Usability Scale (SUS) Questionnaire</h1>
<h2>UniLibrary Usability Evaluation</h2>

<?php if (!empty($success)): ?>
    <div class="success-box"><?php echo $success; ?></div>
    <p style="text-align:center; margin-top:12px; font-size: 13px; color: #666;">
        Thank you for your time and participation.
    </p>
<?php else: ?>

<?php if (!empty($error)): ?>
    <div class="error" style="text-align:center; font-size:14px; margin-bottom:12px;"><?php echo $error; ?></div>
<?php endif; ?>

<h3>Instructions</h3>
<p>
    Please rate the following statements on a scale of 1 to 5, where
    <strong>1 = Strongly Disagree</strong> and <strong>5 = Strongly Agree</strong>.
    There are no right or wrong answers.
</p>

<form method="POST" action="">

<div class="info-grid" style="margin-top: 14px;">
    <span class="label">Participant ID:</span>
    <span class="value"><input type="text" name="participant_id" placeholder="e.g. P1" required style="width: 120px;"></span>
    <span class="label">Date:</span>
    <span class="value"><input type="text" name="survey_date" value="<?php echo date('d F Y'); ?>" required style="width: 180px;"></span>
</div>

<table class="scale-table">
<thead>
    <tr>
        <th>Statement</th>
        <th>1<br><small>SD</small></th>
        <th>2</th>
        <th>3</th>
        <th>4</th>
        <th>5<br><small>SA</small></th>
    </tr>
</thead>
<tbody>
<tr>
  <td>1. I think that I would like to use this system frequently.</td>
  <td><label><input type="radio" name="q1" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q1" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q1" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q1" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q1" value="5" required> 5</label></td>
</tr>
<tr>
  <td>2. I found the system unnecessarily complex.</td>
  <td><label><input type="radio" name="q2" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q2" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q2" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q2" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q2" value="5" required> 5</label></td>
</tr>
<tr>
  <td>3. I thought the system was easy to use.</td>
  <td><label><input type="radio" name="q3" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q3" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q3" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q3" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q3" value="5" required> 5</label></td>
</tr>
<tr>
  <td>4. I think that I would need the support of a technical person to be able to use this system.</td>
  <td><label><input type="radio" name="q4" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q4" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q4" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q4" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q4" value="5" required> 5</label></td>
</tr>
<tr>
  <td>5. I found the various functions in this system were well integrated.</td>
  <td><label><input type="radio" name="q5" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q5" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q5" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q5" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q5" value="5" required> 5</label></td>
</tr>
<tr>
  <td>6. I thought there was too much inconsistency in this system.</td>
  <td><label><input type="radio" name="q6" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q6" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q6" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q6" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q6" value="5" required> 5</label></td>
</tr>
<tr>
  <td>7. I would imagine that most people would learn to use this system very quickly.</td>
  <td><label><input type="radio" name="q7" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q7" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q7" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q7" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q7" value="5" required> 5</label></td>
</tr>
<tr>
  <td>8. I found the system very cumbersome to use.</td>
  <td><label><input type="radio" name="q8" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q8" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q8" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q8" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q8" value="5" required> 5</label></td>
</tr>
<tr>
  <td>9. I felt very confident using the system.</td>
  <td><label><input type="radio" name="q9" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q9" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q9" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q9" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q9" value="5" required> 5</label></td>
</tr>
<tr>
  <td>10. I needed to learn a lot of things before I could get going with this system.</td>
  <td><label><input type="radio" name="q10" value="1" required> 1</label></td>
  <td><label><input type="radio" name="q10" value="2" required> 2</label></td>
  <td><label><input type="radio" name="q10" value="3" required> 3</label></td>
  <td><label><input type="radio" name="q10" value="4" required> 4</label></td>
  <td><label><input type="radio" name="q10" value="5" required> 5</label></td>
</tr>

</tbody>
</table>

<h3>Additional Comments (optional)</h3>
<textarea name="comments" placeholder="Any thoughts or feedback about the system..."></textarea>

<div style="text-align: center; margin-top: 20px;">
    <button type="submit" class="btn">Submit Survey</button>
</div>

</form>
<?php endif; ?>

</body>
</html>