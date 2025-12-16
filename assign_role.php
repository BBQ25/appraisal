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

if(empty($_SESSION["employee_admin"])){
    header("location: dashboard.php");
    exit;
}

$user_id = null;
$employee_cd = $employee_ao = $employee_hr = $employee_admin = $employee_supervisor = 0;
$employee_s = NULL; // Use NULL for supervisor ID if not set

$employee_id_err = $employee_s_err = $user_id_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate user_id
    if(empty($_POST["user_id"]) || !ctype_digit((string)$_POST["user_id"])){
        $user_id_err = "Invalid user id.";
    } else{
        $user_id = (int)$_POST["user_id"];
    }

    // Validate employee_s
    if(empty(trim($_POST["employee_s"]))){
        $employee_s = NULL;
    } else{
        $employee_s = trim($_POST["employee_s"]);
        // Check if supervisor ID exists
        $sql = "SELECT id FROM users WHERE employee_id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_employee_s);
            $param_employee_s = $employee_s;
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) == 0){
                    $employee_s_err = "Supervisor ID does not exist.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Get role checkboxes and supervisor ID
    $employee_admin = isset($_POST["employee_admin"]) ? 1 : 0;
    $employee_cd = isset($_POST["employee_cd"]) ? 1 : 0;
    $employee_ao = isset($_POST["employee_ao"]) ? 1 : 0;
    $employee_hr = isset($_POST["employee_hr"]) ? 1 : 0;
    $employee_supervisor = isset($_POST["employee_supervisor"]) ? 1 : 0;
    $employee_s = !empty(trim($_POST["employee_s"])) ? trim($_POST["employee_s"]) : NULL;

    // Check input errors before updating in database
    if(empty($employee_s_err) && empty($user_id_err)){
        // Prepare an update statement
        $sql = "UPDATE users SET employee_admin = ?, employee_cd = ?, employee_ao = ?, employee_hr = ?, employee_supervisor = ?, employee_s = ? WHERE id = ?";

        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "iiiiisi", $param_employee_admin, $param_employee_cd, $param_employee_ao, $param_employee_hr, $param_employee_supervisor, $param_employee_s, $param_user_id);

            // Set parameters
            $param_employee_admin = $employee_admin;
            $param_employee_cd = $employee_cd;
            $param_employee_ao = $employee_ao;
            $param_employee_hr = $employee_hr;
            $param_employee_supervisor = $employee_supervisor;
            $param_employee_s = $employee_s;
            $param_user_id = $user_id;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Role updated successfully. Redirect to admin dashboard
                $_SESSION["assign_role_success"] = "User roles and supervisor assigned successfully.";
                header("location: admin_dashboard.php");
                exit;
            } else{
                $_SESSION["assign_role_error"] = "Oops! Something went wrong. Please try again later.";
                header("location: admin_dashboard.php");
                exit;
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    } else {
        $_SESSION["assign_role_error"] = trim($user_id_err . " " . $employee_s_err);
        header("location: admin_dashboard.php");
        exit;
    }
    
    // Close connection
    mysqli_close($link);
}
?>
