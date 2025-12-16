<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.html");
    exit;
}

// Only Admins can register new employees
if(empty($_SESSION["employee_admin"])){
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

    // Define variables and initialize with empty values
    $employee_name = $employee_id = "";
    $employee_name_err = $employee_id_err = $employee_s_err = "";
    $employee_admin = $employee_cd = $employee_ao = $employee_hr = $employee_supervisor = 0;
    $employee_s = NULL;
    $flash_message = "";
    $flash_class = "";

    // Processing form data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "POST"){

        // Get role checkboxes and supervisor ID
        $employee_admin = isset($_POST["employee_admin"]) ? 1 : 0;
        $employee_cd = isset($_POST["employee_cd"]) ? 1 : 0;
        $employee_ao = isset($_POST["employee_ao"]) ? 1 : 0;
        $employee_hr = isset($_POST["employee_hr"]) ? 1 : 0;
        $employee_supervisor = isset($_POST["employee_supervisor"]) ? 1 : 0;
        $employee_s = !empty(trim($_POST["employee_s"])) ? trim($_POST["employee_s"]) : NULL;

    // Validate employee_name
    if(empty(trim($_POST["employee_name"]))){
        $employee_name_err = "Please enter an employee name.";
    } else{
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE employee_name = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_employee_name);
            
            // Set parameters
            $param_employee_name = trim($_POST["employee_name"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $employee_name_err = "This employee name is already taken.";
                } else{
                    $employee_name = trim($_POST["employee_name"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate employee_id
    if(empty(trim($_POST["employee_id"]))){
        $employee_id_err = "Please enter an employee ID.";
    } else{
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE employee_id = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_employee_id);
            
            // Set parameters
            $param_employee_id = trim($_POST["employee_id"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    $employee_id_err = "This employee ID is already taken.";
                } else{
                    $employee_id = trim($_POST["employee_id"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
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
    
    // Check input errors before inserting in database
    if(empty($employee_name_err) && empty($employee_id_err) && empty($employee_s_err)){
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (employee_name, employee_id, employee_admin, employee_cd, employee_ao, employee_hr, employee_supervisor, employee_s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssiiiiis", $param_employee_name, $param_employee_id, $param_employee_admin, $param_employee_cd, $param_employee_ao, $param_employee_hr, $param_employee_supervisor, $param_employee_s);
            
            // Set parameters
            $param_employee_name = $employee_name;
            $param_employee_id = $employee_id;
            $param_employee_admin = $employee_admin;
            $param_employee_cd = $employee_cd;
            $param_employee_ao = $employee_ao;
            $param_employee_hr = $employee_hr;
            $param_employee_supervisor = $employee_supervisor;
            $param_employee_s = $employee_s;
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $flash_message = "Registration successful.";
                $flash_class = "alert-success";
                // Clear form fields
                $employee_name = $employee_id = "";
                $employee_admin = $employee_cd = $employee_ao = $employee_hr = $employee_supervisor = 0;
                $employee_s = NULL;
            } else{
                $flash_message = "Something went wrong. Please try again later.";
                $flash_class = "alert-danger";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <p>Please fill this form to create an account.</p>
        <?php if(!empty($flash_message)): ?>
            <div id="flash" class="alert <?php echo $flash_class; ?>"><?php echo htmlspecialchars($flash_message); ?></div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group <?php echo (!empty($employee_name_err)) ? 'has-error' : ''; ?>">
                <label>Employee Name</label>
                <input type="text" id="employee_name" name="employee_name" class="form-control" value="<?php echo $employee_name; ?>">
                <span class="help-block"><?php echo $employee_name_err; ?></span>
            </div>    
            <div class="form-group <?php echo (!empty($employee_id_err)) ? 'has-error' : ''; ?>">
                <label>Employee ID</label>
                <input type="text" id="employee_id" name="employee_id" class="form-control" value="<?php echo $employee_id; ?>">
                <span class="help-block"><?php echo $employee_id_err; ?></span>
            </div>
            <div class="form-group">
                <label>Roles:</label><br>
                <input type="checkbox" id="employee_admin" name="employee_admin" value="1"> <label for="employee_admin">Admin (full access)</label><br>
                <input type="checkbox" id="employee_cd" name="employee_cd" value="1"> <label for="employee_cd">Campus Director</label><br>
                <input type="checkbox" id="employee_ao" name="employee_ao" value="1"> <label for="employee_ao">Administrative Officer</label><br>
                <input type="checkbox" id="employee_hr" name="employee_hr" value="1"> <label for="employee_hr">Human Resource Management Officer</label><br>
                <input type="checkbox" id="employee_supervisor" name="employee_supervisor" value="1"> <label for="employee_supervisor">Supervisor</label><br>
            </div>
            <div class="form-group <?php echo (!empty($employee_s_err)) ? 'has-error' : ''; ?>">
                <label for="employee_s">Supervisor ID:</label>
                <input type="text" id="employee_s" name="employee_s" class="form-control" value="<?php echo $employee_s; ?>">
                <span class="help-block"><?php echo $employee_s_err; ?></span>
            </div>    
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit">
            </div>
            <p>Already have an account? <a href="index.html">Login here</a>.</p>
        </form>
    </div>    
    <script>
        (function(){
            const flash = document.getElementById('flash');
            if(flash){
                setTimeout(()=> {
                    flash.classList.add('fade-out');
                }, 4000);
                setTimeout(()=> {
                    if(flash.parentNode){ flash.parentNode.removeChild(flash); }
                }, 4600);
            }
        })();
    </script>
</body>
</html>
