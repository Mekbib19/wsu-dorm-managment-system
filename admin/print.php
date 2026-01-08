<?php
include __DIR__ . '/../db.php';
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (isset($_POST['submit'])) {
    $username = $_POST['aa'];
    $otp = $_POST['bb'];

    $stmt = $conn->prepare("INSERT INTO otp (id, OTP) VALUES (?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("si", $username, $otp);
    if ($stmt->execute()) {
        echo "OTP sent successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        div{
            margin: 250px;
        }
    </style>
</head>
<body>
    
    <div>
        <hr>
      username : <?php echo isset($_POST['aa']) ? htmlspecialchars($_POST['aa']) : ''; ?>
     <br>
    OTP: <?php echo isset($_POST['bb']) ? htmlspecialchars($_POST['bb']) : ''; ?> <br>
    Register Date : <br> <?php echo date("Y/m/d") ; ?>  <br>
    <br>
    Block: <?php 
        if(isset($_POST['block'])){
            echo htmlspecialchars($_POST['block']);
        } else {
            echo "Not selected";
        }?>
    <br>
    <a onclick="window.print()">Date: 2025/11/22</a>
    <hr>
    </div>
  
    
</body>
</html>