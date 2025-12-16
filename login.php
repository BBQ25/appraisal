<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect him to welcome page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// Include config file
require_once "config.php";

// Define variables and initialize with empty values
    $employee_id = "";
    $employee_id_err = $login_err = "";

    // Processing form data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "POST"){

        // Check if employee_id is empty
        if(empty(trim($_POST["employee_id"]))){
            $employee_id_err = "Please enter employee ID.";
        } else{
            $employee_id = trim($_POST["employee_id"]);
        }

        // Validate credentials
        if(empty($employee_id_err)){
            // Prepare a select statement
            $sql = "SELECT id, employee_id, employee_name, employee_admin, employee_cd, employee_ao, employee_hr, employee_supervisor, employee_s FROM users WHERE employee_id = ?";
            
            if($stmt = mysqli_prepare($link, $sql)){
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "s", $param_employee_id);
                
                // Set parameters
                $param_employee_id = $employee_id;
                
                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)){
                    // Store result
                    mysqli_stmt_store_result($stmt);
                    
                    // Check if employee_id exists
                    if(mysqli_stmt_num_rows($stmt) == 1){
                        // Bind result variables
                        mysqli_stmt_bind_result($stmt, $id, $employee_id, $employee_name, $employee_admin, $employee_cd, $employee_ao, $employee_hr, $employee_supervisor, $employee_s);
                        if(mysqli_stmt_fetch($stmt)){
                            // Employee ID is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["employee_id"] = $employee_id;
                            $_SESSION["employee_name"] = $employee_name;
                            $_SESSION["employee_admin"] = (bool)$employee_admin;
                            $_SESSION["employee_cd"] = $employee_cd;
                            $_SESSION["employee_ao"] = $employee_ao;
                            $_SESSION["employee_hr"] = $employee_hr;
                            $_SESSION["employee_supervisor"] = $employee_supervisor;
                            $_SESSION["employee_s"] = $employee_s;
                            
                            // Redirect user to welcome page
                            header("location: dashboard.php");
                        }
                    } else{
                        // Employee ID doesn't exist, display a generic error message
                        $login_err = "Invalid employee ID.";
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
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
        <title>Login</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="container">
            <h2>Login</h2>
            <p>Please fill in your credentials to login.</p>

            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group <?php echo (!empty($employee_id_err)) ? 'has-error' : ''; ?>">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" class="form-control" value="<?php echo $employee_id; ?>">
                    <span class="help-block"><?php echo $employee_id_err; ?></span>
                </div>    
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Login">
                </div>
                <p>Don't have an account? <a href="register.html">Sign up now</a>.</p>
            </form>
        </div>
    </body>
    </html>
