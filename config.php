<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'user_auth');
define('DB_PASSWORD', 'user_auth');
define('DB_NAME', 'user_auth');

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
?>

