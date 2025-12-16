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

// Check if the user is an Admin, if not then redirect to dashboard
if(empty($_SESSION["employee_admin"])){
    header("location: dashboard.php");
    exit;
}

$users = [];
$supervisors = [];
$sql = "SELECT id, employee_name, employee_id, employee_admin, employee_cd, employee_ao, employee_hr, employee_supervisor, employee_s FROM users";
if($result = mysqli_query($link, $sql)){
    if(mysqli_num_rows($result) > 0){
        while($row = mysqli_fetch_array($result)){
            $users[] = $row;
        }
        mysqli_free_result($result);
    }
}

// Fetch supervisors for dropdown (id + name + employee_id)
$sql_sup = "SELECT id, employee_name, employee_id FROM users WHERE employee_supervisor = 1";
if($result_sup = mysqli_query($link, $sql_sup)){
    if(mysqli_num_rows($result_sup) > 0){
        while($row_sup = mysqli_fetch_array($result_sup)){
            $supervisors[] = $row_sup;
        }
        mysqli_free_result($result_sup);
    }
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .role-select {
            width: 100%;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .btn-update {
            background-color: #28a745;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-update:hover {
            background-color: #218838;
        }
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: none;
            background: #28a745;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            margin-right: 6px;
        }
        .icon-btn.secondary {
            background: #3b82f6;
            text-decoration: none;
        }
        .icon-btn:hover {
            filter: brightness(0.93);
        }
        .supervisor-select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin: 6px 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin Dashboard</h2>
        <?php
        if (isset($_SESSION["assign_role_success"])) {
            echo '<div class="alert alert-success">' . $_SESSION["assign_role_success"] . '</div>';
            unset($_SESSION["assign_role_success"]);
        }
        if (isset($_SESSION["assign_role_error"])) {
            echo '<div class="alert alert-danger">' . $_SESSION["assign_role_error"] . '</div>';
            unset($_SESSION["assign_role_error"]);
        }
        ?>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION["employee_name"]); ?>! You are logged in as an Admin.</p>

        <h3>Manage Users</h3>
        <?php if(!empty($users)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee Name</th>
                            <th>Employee ID</th>
                            <th>Roles</th>
                            <th>Supervisor ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                <td>
                                    <?php
                                        $roles = [];
                                        if($user['employee_admin']) $roles[] = 'Admin';
                                        if($user['employee_cd']) $roles[] = 'Campus Director';
                                        if($user['employee_ao']) $roles[] = 'Administrative Officer';
                                        if($user['employee_hr']) $roles[] = 'Human Resource Management Officer';
                                        if($user['employee_supervisor']) $roles[] = 'Supervisor';
                                        echo empty($roles) ? 'Employee' : implode(', ', $roles);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['employee_s'] ?? 'N/A'); ?></td>
                                <td>
                                    <form action="assign_role.php" method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <label><input type="checkbox" name="employee_admin" value="1" <?php echo ($user['employee_admin']) ? 'checked' : ''; ?>> Admin</label><br>
                                        <label><input type="checkbox" name="employee_cd" value="1" <?php echo ($user['employee_cd']) ? 'checked' : ''; ?>> Campus Director</label><br>
                                        <label><input type="checkbox" name="employee_ao" value="1" <?php echo ($user['employee_ao']) ? 'checked' : ''; ?>> Administrative Officer</label><br>
                                        <label><input type="checkbox" name="employee_hr" value="1" <?php echo ($user['employee_hr']) ? 'checked' : ''; ?>> Human Resource Management Officer</label><br>
                                        <label><input type="checkbox" name="employee_supervisor" value="1" <?php echo ($user['employee_supervisor']) ? 'checked' : ''; ?>> Supervisor</label><br>
                                        <label>Supervisor:</label>
                                        <select name="employee_s" class="supervisor-select">
                                            <option value="">-- Select Supervisor --</option>
                                            <?php foreach($supervisors as $sup): ?>
                                                <option value="<?php echo htmlspecialchars($sup['employee_id']); ?>" <?php echo ($user['employee_s'] == $sup['employee_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sup['employee_name']) . " (ID: " . htmlspecialchars($sup['employee_id']) . ")"; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="icon-btn" title="Update user roles/supervisor">&#128190;</button>
                                    </form>
                                    <?php if($_SESSION["employee_admin"] || $_SESSION["employee_cd"] || $_SESSION["employee_ao"] || $_SESSION["employee_hr"] || $_SESSION["employee_supervisor"]): ?>
                                        <a href="score_employee.php?employee_id=<?php echo $user['id']; ?>" class="icon-btn secondary" title="Score Employee">&#11088;</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>

        <p>
            <a href="dashboard.php" class="btn">Go to User Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Sign Out of Your Account</a>
        </p>
    </div>
</body>
</html>
