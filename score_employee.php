<?php
// Initialize the session
session_start();

// Include config file
require_once "config.php";

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

// Check if the user has scoring privileges (Admin, CD, AO, HR, Supervisor)
if(empty($_SESSION["employee_admin"]) && empty($_SESSION["employee_cd"]) && empty($_SESSION["employee_ao"]) && empty($_SESSION["employee_hr"]) && empty($_SESSION["employee_supervisor"])){
    header("location: dashboard.php");
    exit;
}

// Determine score type based on logged-in user's role
$score_type = "";
if(!empty($_SESSION["employee_admin"])){
    $score_type = "Admin_Score";
} elseif(!empty($_SESSION["employee_cd"])){
    $score_type = "CD_Score";
} elseif($_SESSION["employee_ao"]){
    $score_type = "AO_Score";
} elseif($_SESSION["employee_hr"]){
    $score_type = "HR_Score";
} elseif(!empty($_SESSION["employee_supervisor"])){
    $score_type = "Supervisor_Score";
}

// Helper to return latest saved ratings for this rater/employee (AJAX load)
function fetch_latest_rating($link, $employee_id, $score_type){
    $sql_latest = "SELECT 
        `work_performance_q`,`work_performance_e`,`work_performance_t`,`work_performance_a`,
        `cooperation_teamwork_q`,`cooperation_teamwork_e`,`cooperation_teamwork_t`,`cooperation_teamwork_a`,
        `communication_q`,`communication_e`,`communication_t`,`communication_a`,
        `dependability_attendance_commitment_q`,`dependability_attendance_commitment_e`,`dependability_attendance_commitment_t`,`dependability_attendance_commitment_a`,
        `initiative_q`,`initiative_e`,`initiative_t`,`initiative_a`,
        `professional_presentation_q`,`professional_presentation_e`,`professional_presentation_t`,`professional_presentation_a`,
        total, average, score, comments, recommendation
    FROM performance_scores
    WHERE employee_id = ? AND score_type = ?
    ORDER BY evaluation_date DESC
    LIMIT 1";
    if($stmt_latest = mysqli_prepare($link, $sql_latest)){
        mysqli_stmt_bind_param($stmt_latest, "is", $employee_id, $score_type);
        if(mysqli_stmt_execute($stmt_latest)){
            $res = mysqli_stmt_get_result($stmt_latest);
            if(mysqli_num_rows($res) === 1){
                return mysqli_fetch_array($res, MYSQLI_ASSOC);
            }
        }
        mysqli_stmt_close($stmt_latest);
    }
    return null;
}

// Get employee ID from URL
if(isset($_GET["employee_id"]) && !empty(trim($_GET["employee_id"]))){
    $employee_id_to_score = trim($_GET["employee_id"]);

    // Prepare a select statement to get employee details
    $sql = "SELECT id, employee_name, employee_id, employee_s, employee_supervisor, employee_admin, employee_cd, employee_ao, employee_hr FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $employee_id_to_score;

        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) == 1){
                $employee_details = mysqli_fetch_array($result, MYSQLI_ASSOC);
                // Prevent self-rating
                if(!empty($_SESSION["id"]) && $employee_details["id"] == $_SESSION["id"]){
                    header("location: dashboard.php");
                    exit();
                }

                // No one rates Admin accounts
                if(!empty($employee_details["employee_admin"])){
                    header("location: dashboard.php");
                    exit();
                }

                // If scorer is Supervisor only, ensure this employee is assigned to them
                $is_admin_or_global = !empty($_SESSION["employee_admin"]) || !empty($_SESSION["employee_cd"]) || !empty($_SESSION["employee_ao"]) || !empty($_SESSION["employee_hr"]);
                if(!$is_admin_or_global && !empty($_SESSION["employee_supervisor"])){
                    if($employee_details["employee_s"] !== $_SESSION["employee_id"]){
                        header("location: dashboard.php");
                        exit();
                    }
                }

                // CD/AO/HR cannot rate supervisors
                if(($score_type === "CD_Score" || $score_type === "AO_Score" || $score_type === "HR_Score") && !empty($employee_details["employee_supervisor"])){
                    header("location: dashboard.php");
                    exit();
                }

                // CD cannot rate CD/AO/HR; AO cannot rate CD/HR; HR cannot rate CD/AO
                if($score_type === "CD_Score" && (!empty($employee_details["employee_cd"]) || !empty($employee_details["employee_ao"]) || !empty($employee_details["employee_hr"]))){
                    header("location: dashboard.php");
                    exit();
                }
                if($score_type === "AO_Score" && (!empty($employee_details["employee_cd"]) || !empty($employee_details["employee_hr"]))){
                    header("location: dashboard.php");
                    exit();
                }
                if($score_type === "HR_Score" && (!empty($employee_details["employee_cd"]) || !empty($employee_details["employee_ao"]))){
                    header("location: dashboard.php");
                    exit();
                }

                // AJAX fetch of latest rating for this rater/employee
                if(isset($_GET["fetch_latest"]) && $_GET["fetch_latest"] === "1"){
                    $latest = fetch_latest_rating($link, $employee_details["id"], $score_type);
                    header('Content-Type: application/json');
                    echo json_encode(['status'=>'ok','data'=>$latest]);
                    exit;
                }
            } else{
                // Employee ID not found
                header("location: admin_dashboard.php");
                exit();
            }
        } else{
            echo "Oops! Something went wrong. Please try again later.";
        }
        mysqli_stmt_close($stmt);
    }
} else{
    // Employee ID not provided in URL
    header("location: admin_dashboard.php");
    exit();
}

// Define variables and initialize with empty values
$score = "";
$score_err = "";
$success_message = "";
$draft_key = "score_draft";
$draft_values = $_SESSION[$draft_key][$employee_id_to_score] ?? [];

// Handle autosave via AJAX to keep progress if page refreshes
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["autosave"])){
    $rows_keys = ['work','team','comm','dep','init','prof'];
    $saved = [];
    foreach ($rows_keys as $rk) {
        foreach (['q','e','t'] as $metric) {
            $field = "{$rk}_{$metric}";
            if(isset($_POST[$field])) {
                $saved[$field] = trim($_POST[$field]);
            }
        }
    }
    $_SESSION[$draft_key][$employee_id_to_score] = $saved;
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']);
    exit;
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate factor ratings and compute averages server-side
    $factors = [
        'work' => 'Work Performance',
        'team' => 'Cooperation & Teamwork',
        'comm' => 'Communication',
        'dep' => 'Dependability (Attendance & Commitment)',
        'init' => 'Initiative',
        'prof' => 'Professional Presentation'
    ];

    $ratings = [];
    foreach ($factors as $key => $label) {
        foreach (['q','e','t'] as $metric) {
            $field = "{$key}_{$metric}";
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : "";
            if($val === "" || !ctype_digit($val) || (int)$val < 1 || (int)$val > 5){
                $score_err = "Please rate all factors (1-5). Missing/invalid: {$label} - " . strtoupper($metric);
                break 2;
            }
            $ratings[$key][$metric] = (int)$val;
        }
        $ratings[$key]['a'] = round(($ratings[$key]['q'] + $ratings[$key]['e'] + $ratings[$key]['t']) / 3, 2);
    }

    if(empty($score_err)){
        $row_avgs = array_column($ratings, 'a');
        $total_a = array_sum($row_avgs);
        $avg_a = round($total_a / count($row_avgs), 2);
        $score = (int)round($avg_a * 20); // scale 1-5 to 0-100

        // Prepare an insert statement matching expanded schema
        $sql = "INSERT INTO performance_scores (
            employee_id, evaluator_id, score_type,
            `work_performance_q`, `work_performance_e`, `work_performance_t`, `work_performance_a`,
            `cooperation_teamwork_q`, `cooperation_teamwork_e`, `cooperation_teamwork_t`, `cooperation_teamwork_a`,
            `communication_q`, `communication_e`, `communication_t`, `communication_a`,
            `dependability_attendance_commitment_q`, `dependability_attendance_commitment_e`, `dependability_attendance_commitment_t`, `dependability_attendance_commitment_a`,
            `initiative_q`, `initiative_e`, `initiative_t`, `initiative_a`,
            `professional_presentation_q`, `professional_presentation_e`, `professional_presentation_t`, `professional_presentation_a`,
            total, average, score, evaluation_date
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, NOW()
        )";

        if($stmt = mysqli_prepare($link, $sql)){
            // Flatten ratings into variables for bind_param (needs references)
            $work_q = $ratings['work']['q']; $work_e = $ratings['work']['e']; $work_t = $ratings['work']['t']; $work_a = $ratings['work']['a'];
            $team_q = $ratings['team']['q']; $team_e = $ratings['team']['e']; $team_t = $ratings['team']['t']; $team_a = $ratings['team']['a'];
            $comm_q = $ratings['comm']['q']; $comm_e = $ratings['comm']['e']; $comm_t = $ratings['comm']['t']; $comm_a = $ratings['comm']['a'];
            $dep_q = $ratings['dep']['q']; $dep_e = $ratings['dep']['e']; $dep_t = $ratings['dep']['t']; $dep_a = $ratings['dep']['a'];
            $init_q = $ratings['init']['q']; $init_e = $ratings['init']['e']; $init_t = $ratings['init']['t']; $init_a = $ratings['init']['a'];
            $prof_q = $ratings['prof']['q']; $prof_e = $ratings['prof']['e']; $prof_t = $ratings['prof']['t']; $prof_a = $ratings['prof']['a'];

            $types = "iis" . str_repeat("iiid", 6) . "ddi"; // 3 initial + (6 rows * 4 cols) + total/avg/score
            mysqli_stmt_bind_param(
                $stmt,
                $types,
                $param_employee_id,
                $param_evaluator_id,
                $param_score_type,
                $work_q, $work_e, $work_t, $work_a,
                $team_q, $team_e, $team_t, $team_a,
                $comm_q, $comm_e, $comm_t, $comm_a,
                $dep_q, $dep_e, $dep_t, $dep_a,
                $init_q, $init_e, $init_t, $init_a,
                $prof_q, $prof_e, $prof_t, $prof_a,
                $total_a,
                $avg_a,
                $score
            );

            // Set parameters
            $param_employee_id = $employee_details["id"];
            $param_evaluator_id = $_SESSION["id"];
            $param_score_type = $score_type;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $new_score_id = mysqli_insert_id($link);
                // clear draft for this employee
                unset($_SESSION[$draft_key][$employee_details["id"]]);
                header("location: score_comments.php?score_id=" . $new_score_id);
                exit;
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Employee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Score Employee: <?php echo htmlspecialchars($employee_details["employee_name"]); ?> (ID: <?php echo htmlspecialchars($employee_details["employee_id"]); ?>)</h2>
        <p><strong>Legend:</strong> Q = Quality, E = Efficiency, T = Timeliness, A = Average<br>
        <strong>Numerical &amp; Adjectival Rating:</strong> 5 – Outstanding, 4 – Very Satisfactory, 3 – Satisfactory, 2 – Unsatisfactory, 1 – Poor</p>
        <p>As a <?php echo str_replace("_", " ", str_replace("Score", "", $score_type)); ?>, rate each factor (1–Poor to 5–Outstanding). Overall score is averaged.</p>

        <?php
        if(!empty($success_message)){
            echo '<div class="alert alert-success">' . $success_message . '</div>';
        }
        ?>

        <form id="score-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?employee_id=" . $employee_details["id"]; ?>" method="post">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Evaluation Factors</th>
                            <th style="width: 60px;">Q</th>
                            <th style="width: 60px;">E</th>
                            <th style="width: 60px;">T</th>
                            <th style="width: 60px;">A</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><th colspan="5">Performance Skill Factors</th></tr>
                        <?php
                        $rows = [
                            ['key' => 'work', 'label' => 'Work Performance', 'desc' => 'Performs responsibilities with utmost accountability and commits to achieving the goals of SLSU'],
                            ['key' => 'team', 'label' => 'Cooperation & Teamwork', 'desc' => 'Integrates own activities with fellow employees, readily offers and accepts assistance to accomplish tasks and demonstrates cooperativeness'],
                            ['key' => 'comm', 'label' => 'Communication', 'desc' => 'Communicates effectively (written, oral, presentation) with clients, customers & staff.'],
                        ];
                        foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?php echo $row['label']; ?></strong><br><small><?php echo $row['desc']; ?></small></td>
                            <?php foreach (['q','e','t'] as $metric): ?>
                                <td>
                                    <?php $saved_val = $draft_values[$row['key'].'_'.$metric] ?? ''; ?>
                                    <select id="<?php echo $row['key'].'_'.$metric; ?>" name="<?php echo $row['key'].'_'.$metric; ?>" required>
                                        <option value="">-</option>
                                        <?php for ($i=5; $i>=1; $i--): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ((string)$saved_val === (string)$i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <span class="avg-display" data-row="<?php echo $row['key']; ?>">--</span>
                                <input type="hidden" id="<?php echo $row['key']; ?>_a" name="<?php echo $row['key']; ?>_a" value="">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr><th colspan="5">Performance Traits Factors</th></tr>
                        <?php
                        $rows2 = [
                            ['key' => 'dep', 'label' => 'Dependability (Attendance & Commitment)', 'desc' => 'Reports on time; uses time productively.'],
                            ['key' => 'init', 'label' => 'Initiative', 'desc' => 'Anticipates required tasks & acts accordingly; demonstrates willingness & ability to take risks; makes creative uses of available resources to deliver assigned tasks'],
                            ['key' => 'prof', 'label' => 'Professional Presentation', 'desc' => 'Demonstrates a high level of professionalism like mutual trust & support for fellow employer, integrity & dedication to the organization'],
                        ];
                        foreach ($rows2 as $row): ?>
                        <tr>
                            <td><strong><?php echo $row['label']; ?></strong><br><small><?php echo $row['desc']; ?></small></td>
                            <?php foreach (['q','e','t'] as $metric): ?>
                                <td>
                                    <?php $saved_val2 = $draft_values[$row['key'].'_'.$metric] ?? ''; ?>
                                    <select id="<?php echo $row['key'].'_'.$metric; ?>" name="<?php echo $row['key'].'_'.$metric; ?>" required>
                                        <option value="">-</option>
                                        <?php for ($i=5; $i>=1; $i--): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ((string)$saved_val2 === (string)$i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <span class="avg-display" data-row="<?php echo $row['key']; ?>">--</span>
                                <input type="hidden" id="<?php echo $row['key']; ?>_a" name="<?php echo $row['key']; ?>_a" value="">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total / Avg</th>
                            <th colspan="4" id="total-cell">--</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <input type="hidden" name="score" id="overall_score" value="">
            <input type="hidden" name="total_a" id="total_a" value="">
            <input type="hidden" name="avg_a" id="avg_a" value="">
            <div class="form-group <?php echo (!empty($score_err)) ? 'has-error' : ''; ?>">
                <span class="help-block"><?php echo $score_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit Score">
                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        (function() {
            const form = document.getElementById('score-form');
            if (!form) return;

            const rows = ['work','team','comm','dep','init','prof'];
            const totalCell = document.getElementById('total-cell');
            const hiddenScore = document.getElementById('overall_score');
            const hiddenTotalA = document.getElementById('total_a');
            const hiddenAvgA = document.getElementById('avg_a');
            let autosaveTimer = null;
            const urlParams = new URLSearchParams(window.location.search);
            const fetchUrl = `${window.location.pathname}?${urlParams.toString()}&fetch_latest=1`;

            const saveDraft = () => {
                if (autosaveTimer) clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => {
                    const formData = new FormData(form);
                    formData.append('autosave', '1');
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }).catch(() => {});
                }, 350);
            };

            const computeRowAvg = (rowKey) => {
                const q = parseInt(document.getElementById(`${rowKey}_q`)?.value || '', 10);
                const e = parseInt(document.getElementById(`${rowKey}_e`)?.value || '', 10);
                const t = parseInt(document.getElementById(`${rowKey}_t`)?.value || '', 10);
                if ([q,e,t].every(v => !isNaN(v))) {
                    const avg = ((q + e + t) / 3).toFixed(2);
                    const display = document.querySelector(`.avg-display[data-row="${rowKey}"]`);
                    const hidden = document.getElementById(`${rowKey}_a`);
                    if (display) display.textContent = avg;
                    if (hidden) hidden.value = avg;
                    return parseFloat(avg);
                } else {
                    const display = document.querySelector(`.avg-display[data-row="${rowKey}"]`);
                    const hidden = document.getElementById(`${rowKey}_a`);
                    if (display) display.textContent = '--';
                    if (hidden) hidden.value = '';
                    return null;
                }
            };

            const updateTotals = () => {
                let rowAvgs = [];
                rows.forEach(r => {
                    const avg = computeRowAvg(r);
                    if (avg !== null) rowAvgs.push(avg);
                });
                if (rowAvgs.length === rows.length) {
                    const sum = rowAvgs.reduce((a,b)=>a+b,0);
                    const avg = (sum / rowAvgs.length).toFixed(2);
                    totalCell.textContent = `Sum of A: ${sum.toFixed(2)} | Avg: ${avg}`;
                    if (hiddenScore) hiddenScore.value = Math.round(parseFloat(avg) * 20); // scale 1-5 to 0-100
                    if (hiddenTotalA) hiddenTotalA.value = sum.toFixed(2);
                    if (hiddenAvgA) hiddenAvgA.value = avg;
                    saveDraft();
                } else {
                    totalCell.textContent = '--';
                    if (hiddenScore) hiddenScore.value = '';
                    if (hiddenTotalA) hiddenTotalA.value = '';
                    if (hiddenAvgA) hiddenAvgA.value = '';
                }
            };

            rows.forEach(r => {
                ['q','e','t'].forEach(metric => {
                    const sel = document.getElementById(`${r}_${metric}`);
                    if (sel) sel.addEventListener('change', updateTotals);
                });
            });

            const populateFromLatest = (data) => {
                if(!data) return;
                const map = [
                    ['work_q','work_performance_q'],
                    ['work_e','work_performance_e'],
                    ['work_t','work_performance_t'],
                    ['team_q','cooperation_teamwork_q'],
                    ['team_e','cooperation_teamwork_e'],
                    ['team_t','cooperation_teamwork_t'],
                    ['comm_q','communication_q'],
                    ['comm_e','communication_e'],
                    ['comm_t','communication_t'],
                    ['dep_q','dependability_attendance_commitment_q'],
                    ['dep_e','dependability_attendance_commitment_e'],
                    ['dep_t','dependability_attendance_commitment_t'],
                    ['init_q','initiative_q'],
                    ['init_e','initiative_e'],
                    ['init_t','initiative_t'],
                    ['prof_q','professional_presentation_q'],
                    ['prof_e','professional_presentation_e'],
                    ['prof_t','professional_presentation_t'],
                ];
                map.forEach(([fieldId, dbCol]) => {
                    const sel = document.getElementById(fieldId);
                    if(sel && data[dbCol] !== null && data[dbCol] !== undefined){
                        sel.value = data[dbCol];
                    }
                });
                // Hidden totals/avg if present
                if(data.total !== undefined && hiddenTotalA) hiddenTotalA.value = data.total;
                if(data.average !== undefined && hiddenAvgA) hiddenAvgA.value = data.average;
                if(data.score !== undefined && hiddenScore) hiddenScore.value = data.score;
                const comments = document.getElementById('comments');
                if(comments && data.comments !== undefined && data.comments !== null){
                    comments.value = data.comments;
                }
                if(data.recommendation){
                    const radios = document.querySelectorAll('input[name=\"recommendation\"]');
                    radios.forEach(r => {
                        if(r.value === data.recommendation){ r.checked = true; }
                    });
                }
                updateTotals();
            };

            // Fetch latest saved rating for this rater/employee
            fetch(fetchUrl, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if(resp && resp.status === 'ok' && resp.data){
                        populateFromLatest(resp.data);
                    }
                })
                .catch(()=>{ updateTotals(); });

            // Recompute on load (restores averages from saved values)
            updateTotals();

            form.addEventListener('submit', (e) => {
                updateTotals();
                if (!hiddenScore.value) {
                    e.preventDefault();
                    alert('Please rate all factors before submitting.');
                }
            });
        })();
    </script>
</body>
</html>
