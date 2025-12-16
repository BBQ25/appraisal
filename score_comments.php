<?php
// Initialize the session
session_start();

// Include config file
require_once "config.php";

// Check if the user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

// Require scoring privileges
if(empty($_SESSION["employee_admin"]) && empty($_SESSION["employee_cd"]) && empty($_SESSION["employee_ao"]) && empty($_SESSION["employee_hr"]) && empty($_SESSION["employee_supervisor"])){
    header("location: dashboard.php");
    exit;
}

$score_id = isset($_GET["score_id"]) ? (int)$_GET["score_id"] : 0;
$score_entry = null;

if($score_id > 0){
    $sql = "SELECT ps.*, u.employee_name, u.employee_id AS eid FROM performance_scores ps JOIN users u ON ps.employee_id = u.id WHERE ps.id = ?";
    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $score_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) === 1){
                $score_entry = mysqli_fetch_array($result, MYSQLI_ASSOC);
                // Ensure the evaluator matches (unless admin-like roles)
                $is_admin_or_global = !empty($_SESSION["employee_admin"]) || !empty($_SESSION["employee_cd"]) || !empty($_SESSION["employee_ao"]) || !empty($_SESSION["employee_hr"]);
                if(!$is_admin_or_global && $score_entry["evaluator_id"] != $_SESSION["id"]){
                    header("location: dashboard.php");
                    exit;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if(!$score_entry){
    header("location: dashboard.php");
    exit;
}

$comments_err = $rec_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $comments = trim($_POST["comments"] ?? "");
    $recommendation = trim($_POST["recommendation"] ?? "");

    if($comments === ""){
        $comments_err = "Please add a comment.";
    }

    if($recommendation === ""){
        $rec_err = "Please select a recommendation.";
    } elseif(!in_array($recommendation, ["For renewal", "Non-renewal"])){
        $rec_err = "Invalid recommendation.";
    }

    if(empty($comments_err) && empty($rec_err)){
        $sql = "UPDATE performance_scores SET comments = ?, recommendation = ? WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "ssi", $comments, $recommendation, $score_id);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION["score_success"] = "Score and recommendation submitted for " . htmlspecialchars($score_entry["employee_name"]) . ".";
                header("location: dashboard.php");
                exit;
            } else {
                $comments_err = "Update failed. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Comments</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .comment-box {
            width: 100%;
            min-height: 160px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 1em;
            resize: vertical;
        }
        .inline-options {
            display: flex;
            gap: 18px;
            margin-top: 12px;
        }
        .inline-options label {
            font-weight: 600;
            color: var(--text);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Comments & Recommendation</h2>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($score_entry["employee_name"]); ?> (ID: <?php echo htmlspecialchars($score_entry["eid"]); ?>)</p>
        <p>Add development comments and choose a recommendation to complete this evaluation.</p>

        <form action="score_comments.php?score_id=<?php echo $score_id; ?>" method="post">
            <div class="form-group <?php echo (!empty($comments_err)) ? 'has-error' : ''; ?>">
                <label for="comments"><strong>Comments for Development Purposes:</strong></label>
                <textarea id="comments" name="comments" class="comment-box" required><?php echo htmlspecialchars($_POST["comments"] ?? $score_entry["comments"] ?? ""); ?></textarea>
                <span class="help-block"><?php echo $comments_err; ?></span>
            </div>

            <div class="form-group <?php echo (!empty($rec_err)) ? 'has-error' : ''; ?>">
                <label><strong>Recommendation:</strong></label>
                <div class="inline-options">
                    <label><input type="radio" name="recommendation" value="For renewal" <?php echo (($_POST["recommendation"] ?? $score_entry["recommendation"] ?? "") === "For renewal") ? "checked" : ""; ?>> For renewal</label>
                    <label><input type="radio" name="recommendation" value="Non-renewal" <?php echo (($_POST["recommendation"] ?? $score_entry["recommendation"] ?? "") === "Non-renewal") ? "checked" : ""; ?>> Non-renewal</label>
                </div>
                <span class="help-block"><?php echo $rec_err; ?></span>
            </div>

            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit Recommendation">
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
