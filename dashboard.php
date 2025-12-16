<?php
// Initialize the session
session_start();

// Include config file
require_once "config.php";

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

$employee_scores = [];
$is_global_rater = !empty($_SESSION["employee_admin"]) || !empty($_SESSION["employee_cd"]) || !empty($_SESSION["employee_ao"]) || !empty($_SESSION["employee_hr"]);
$current_score_type = "";
if(!empty($_SESSION["employee_cd"])) $current_score_type = "CD_Score";
elseif(!empty($_SESSION["employee_ao"])) $current_score_type = "AO_Score";
elseif(!empty($_SESSION["employee_hr"])) $current_score_type = "HR_Score";
elseif(!empty($_SESSION["employee_supervisor"])) $current_score_type = "Supervisor_Score";
$admin_all_scores = [];
$sql = "SELECT ps1.score_type, ps1.score, ps1.evaluation_date
        FROM performance_scores ps1
        JOIN (
            SELECT score_type, MAX(evaluation_date) AS maxdate
            FROM performance_scores
            WHERE employee_id = ?
            GROUP BY score_type
        ) latest ON latest.score_type = ps1.score_type AND latest.maxdate = ps1.evaluation_date
        WHERE ps1.employee_id = ?
        ORDER BY ps1.evaluation_date DESC";

if($stmt = mysqli_prepare($link, $sql)){
    mysqli_stmt_bind_param($stmt, "ii", $param_employee_id_latest, $param_employee_id);
    $param_employee_id_latest = $_SESSION["id"];
    $param_employee_id = $_SESSION["id"];

    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $employee_scores[] = $row;
        }
    } else{
        echo "Oops! Something went wrong. Unable to fetch scores.";
    }
    mysqli_stmt_close($stmt);
}

// Admin view: latest scores per role for all employees
if(!empty($_SESSION["employee_admin"])){
    $sql_admin = "SELECT 
        u.id,
        u.employee_name,
        u.employee_id,
        (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id AND ps.score_type = 'CD_Score' ORDER BY ps.evaluation_date DESC LIMIT 1) AS cd_avg,
        (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id AND ps.score_type = 'AO_Score' ORDER BY ps.evaluation_date DESC LIMIT 1) AS ao_avg,
        (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id AND ps.score_type = 'HR_Score' ORDER BY ps.evaluation_date DESC LIMIT 1) AS hr_avg,
        (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id AND ps.score_type = 'Supervisor_Score' ORDER BY ps.evaluation_date DESC LIMIT 1) AS sup_avg
        FROM users u";
    if($stmt_admin = mysqli_prepare($link, $sql_admin)){
        if(mysqli_stmt_execute($stmt_admin)){
            $result_admin = mysqli_stmt_get_result($stmt_admin);
            while($row_admin = mysqli_fetch_array($result_admin, MYSQLI_ASSOC)){
                $admin_all_scores[] = $row_admin;
            }
        }
        mysqli_stmt_close($stmt_admin);
    }
}

$user_roles = [];
if(isset($_SESSION["employee_admin"]) && $_SESSION["employee_admin"]) $user_roles[] = "Admin";
if(isset($_SESSION["employee_cd"]) && $_SESSION["employee_cd"]) $user_roles[] = "Campus Director";
if(isset($_SESSION["employee_ao"]) && $_SESSION["employee_ao"]) $user_roles[] = "Administrative Officer";
if(isset($_SESSION["employee_hr"]) && $_SESSION["employee_hr"]) $user_roles[] = "Human Resource Management Officer";
if(isset($_SESSION["employee_supervisor"]) && $_SESSION["employee_supervisor"]) $user_roles[] = "Supervisor";
if(empty($user_roles)) $user_roles[] = "Employee";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION["employee_name"] ?? 'Guest'); ?>!</h2>
        <p>You are now logged in to your account.</p>
        <p class="inline-row"><strong>Your roles:</strong>
            <?php foreach($user_roles as $role): ?>
                <span class="badge"><?php echo htmlspecialchars($role); ?></span>
            <?php endforeach; ?>
        </p>
        <?php if(isset($_SESSION["employee_s"]) && $_SESSION["employee_s"] !== null) echo "<p>Your Supervisor ID: " . htmlspecialchars($_SESSION["employee_s"]) . "</p>"; ?>

        <div class="section-spaced">
            <div class="inline-row">
                <strong>Your Performance Scores:</strong>
                <?php if(!empty($employee_scores)): ?>
                    <?php foreach($employee_scores as $score_entry): ?>
                        <span class="badge soft"><?php echo htmlspecialchars(str_replace("_", " ", str_replace("Score", "", $score_entry['score_type']))); ?>: <?php echo htmlspecialchars($score_entry['score']); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted">No performance scores available yet.</span>
                <?php endif; ?>
            </div>
            <?php if(!empty($employee_scores)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Score Type</th>
                                <th>Score</th>
                                <th>Evaluation Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employee_scores as $score_entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(str_replace("_", " ", str_replace("Score", "", $score_entry['score_type']))); ?></td>
                                    <td><?php echo htmlspecialchars($score_entry['score']); ?></td>
                                    <td><?php echo htmlspecialchars($score_entry['evaluation_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Global raters (Admin/CD/AO/HR) can rate all employees
        if ($is_global_rater) {
            $all_employees = [];
            if(!empty($_SESSION["employee_admin"])){
                // Admin sees latest average (any score type) for quick overview
                $sql_all = "SELECT u.id, u.employee_id, u.employee_name, u.employee_supervisor, u.employee_cd, u.employee_ao, u.employee_hr,
                (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id ORDER BY ps.evaluation_date DESC LIMIT 1) AS latest_score FROM users u";
            } else {
                // CD/AO/HR see only their own score type
                $sql_all = "SELECT u.id, u.employee_id, u.employee_name, u.employee_supervisor, u.employee_cd, u.employee_ao, u.employee_hr,
                (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id AND ps.score_type = ? ORDER BY ps.evaluation_date DESC LIMIT 1) AS latest_score FROM users u";
            }
            if ($stmt_all = mysqli_prepare($link, $sql_all)) {
                if(empty($_SESSION["employee_admin"])){
                    mysqli_stmt_bind_param($stmt_all, "s", $current_score_type);
                }
                if (mysqli_stmt_execute($stmt_all)) {
                    $result_all = mysqli_stmt_get_result($stmt_all);
                    while ($row_all = mysqli_fetch_array($result_all, MYSQLI_ASSOC)) {
                        $all_employees[] = $row_all;
                    }
                }
                mysqli_stmt_close($stmt_all);
            }
        ?>
            <div class="section-spaced">
                <h3>All Employees (rate any)</h3>
                <?php if (!empty($all_employees)): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>Attained Score</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['employee_name']); ?></td>
                                        <td><?php echo $employee['latest_score'] !== null ? htmlspecialchars($employee['latest_score']) : 'Not rated'; ?></td>
                                        <td>
                                            <?php
                                                $can_rate = true;
                                                if(!empty($_SESSION["employee_admin"])){
                                                    $can_rate = true;
                                                } else {
                                                    // block rating admins
                                                    if(!empty($employee['employee_admin'])) $can_rate = false;
                                                    // block rating supervisors
                                                    if(!empty($employee['employee_supervisor'])) $can_rate = false;
                                                    // role-specific blocks
                                                    if($current_score_type === "CD_Score" && (!empty($employee['employee_cd']) || !empty($employee['employee_ao']) || !empty($employee['employee_hr']))) $can_rate = false;
                                                    if($current_score_type === "AO_Score" && (!empty($employee['employee_cd']) || !empty($employee['employee_hr']))) $can_rate = false;
                                                    if($current_score_type === "HR_Score" && (!empty($employee['employee_cd']) || !empty($employee['employee_ao']))) $can_rate = false;
                                                }
                                            ?>
                                            <?php if($can_rate): ?>
                                                <a class="btn btn-secondary" href="score_employee.php?employee_id=<?php echo $employee['id']; ?>">Rate</a>
                                            <?php else: ?>
                                                <span class="text-muted">Cannot rate</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No employees found.</p>
                <?php endif; ?>
            </div>
        <?php
        }

        // Supervisors can rate assigned employees
        if (!empty($_SESSION["employee_supervisor"]) && isset($_SESSION["employee_id"])) {
            // Fetch employees assigned to this supervisor
            $assigned_employees = [];
            $supervisor_id = $_SESSION["employee_id"];
            $sql_assigned = "SELECT u.id, u.employee_id, u.employee_name, (SELECT ps.average FROM performance_scores ps WHERE ps.employee_id = u.id ORDER BY ps.evaluation_date DESC LIMIT 1) AS latest_score FROM users u WHERE u.employee_s = ?";

            if ($stmt_assigned = mysqli_prepare($link, $sql_assigned)) {
                mysqli_stmt_bind_param($stmt_assigned, "s", $param_supervisor_id);
                $param_supervisor_id = $supervisor_id;

                if (mysqli_stmt_execute($stmt_assigned)) {
                    $result_assigned = mysqli_stmt_get_result($stmt_assigned);
                    while ($row_assigned = mysqli_fetch_array($result_assigned, MYSQLI_ASSOC)) {
                        $assigned_employees[] = $row_assigned;
                    }
                } else {
                    echo "Oops! Something went wrong. Unable to fetch assigned employees.";
                }
                mysqli_stmt_close($stmt_assigned);
            }
        ?>
            <div class="section-spaced">
            <h3>Your Assigned Employees</h3>
            <?php if (!empty($assigned_employees)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Attained Score</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['employee_name']); ?></td>
                                    <td><?php echo $employee['latest_score'] !== null ? htmlspecialchars($employee['latest_score']) : 'Not rated'; ?></td>
                                    <td><a class="btn btn-secondary" href="score_employee.php?employee_id=<?php echo $employee['id']; ?>">Rate</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No employees assigned to you.</p>
            <?php endif; ?>
            </div>
        <?php
        }
        ?>

        <?php if(isset($_SESSION["employee_admin"]) && $_SESSION["employee_admin"]): ?>
            <?php if(!empty($admin_all_scores)): ?>
                <div class="section-spaced">
                    <h3>All Employees - Latest Scores (Admin view)</h3>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>CD Average</th>
                                    <th>AO Average</th>
                                    <th>HR Average</th>
                                    <th>Supervisor Average</th>
                                    <th>Final Average</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($admin_all_scores as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['employee_name']); ?></td>
                                        <?php
                                            $avg_parts = [];
                                            $avg_labels = [];
                                            if($emp['cd_avg'] !== null){ $avg_parts[] = (float)$emp['cd_avg']; $avg_labels[] = 'CD'; }
                                            if($emp['ao_avg'] !== null){ $avg_parts[] = (float)$emp['ao_avg']; $avg_labels[] = 'AO'; }
                                            if($emp['hr_avg'] !== null){ $avg_parts[] = (float)$emp['hr_avg']; $avg_labels[] = 'HR'; }
                                            if($emp['sup_avg'] !== null){ $avg_parts[] = (float)$emp['sup_avg']; $avg_labels[] = 'Supervisor'; }
                                            $combined_avg = !empty($avg_parts) ? round(array_sum($avg_parts)/count($avg_parts), 2) : null;
                                        ?>
                                        <td><?php echo $emp['cd_avg'] !== null ? htmlspecialchars($emp['cd_avg']) : '—'; ?></td>
                                        <td><?php echo $emp['ao_avg'] !== null ? htmlspecialchars($emp['ao_avg']) : '—'; ?></td>
                                        <td><?php echo $emp['hr_avg'] !== null ? htmlspecialchars($emp['hr_avg']) : '—'; ?></td>
                                        <td><?php echo $emp['sup_avg'] !== null ? htmlspecialchars($emp['sup_avg']) : '—'; ?></td>
                                        <td><?php echo $combined_avg !== null ? htmlspecialchars($combined_avg) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <p><a href="admin_dashboard.php" class="btn">Admin Dashboard</a></p>
            <p><a href="register.php" class="btn">Register New Employee</a></p>
        <?php endif; ?>
        <p>
            <a href="logout.php" class="btn btn-danger">Sign Out of Your Account</a>
        </p>
    </div>
    <?php if(isset($link) && $link){ mysqli_close($link); } ?>
</body>
</html>
