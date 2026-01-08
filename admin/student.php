<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<div style="text-align:right; margin-bottom:20px;" align="right" padding="20px;">

        <button onclick="location.href='../logout.php'" style="color:#ff0000;">Logout</button>
        <button onclick="location.href='dashboard.php'" style="color:#ff0000;">Dashboard</button>
    </div>
<body>
    <h3>Add Student</h3>
    <form action="print.php" method="post">
        username: <br>
        <input name="aa" type='text' required><br>
        OTP: <br>
        <input name="bb" type='text' required><br>
        <select name="" id="">
            <option value="">Select Block</option>
            <option>Block 1</option>
            <option>Block 2</option>
            <option>Block 3</option>
        </select>

        <br>
        <input type="submit" value="Send" name="submit">
        <input type="reset" value="Cancel">

    </form>
    <a href="student_view.html">Students</a>


</html>