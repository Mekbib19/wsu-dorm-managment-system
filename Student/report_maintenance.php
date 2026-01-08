<?php include __DIR__.'/../db.php'; 
session_start();
// For now - fake student id=1; later use session
if(isset($_SESSION['student_id'])){
    $student_id = $_SESSION['student_id'];

}
else{
    header("Location:student_dashboard.php");
}
$stmt = $conn->prepare(" 
            SELECT room_number FROM students WHERE id=? 
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    if(!isset($reports)){
       header("Location:student_dashboard.php"); 
    }
    if ( $reports->fetch_assoc()['room_number']=='null'){
        header('Location:student_dashboard.php');
    }
?>
<!DOCTYPE html>
<html>
<head><title>Report Problem</title></head>
<body>

<h3>Report Maintenance Issue</h3>

<form method="post">
    Problem Type:<br>
    <select name="type" required>
        <option value="">-- Select --</option>
        <option>Socket/Electricity</option>
        <option>Window/Broken glass</option>
        <option>Door lock</option>
        <option>Water problem</option>
        <option>Other</option>
    </select><br><br>
    
    Description:<br>
    <textarea name="description" rows="4" cols="40" required></textarea><br><br>
    
    <button type="submit" name="report">Submit Report</button>
</form>

<?php
if (isset($_POST['report'])) {
    $type = $_POST['type'];
    $desc = $_POST['description'];

    $stmt = $conn->prepare("
        INSERT INTO maintenance_reports 
        (student_id, type, description, block_id, room_number) 
        VALUES (?, ?, ?, 
            (SELECT block_id FROM students WHERE id=?), 
            (SELECT room_number FROM students WHERE id=?)
        )
    ");
    $stmt->bind_param("isiii", $student_id, $type, $desc, $student_id, $student_id);

    if ($stmt->execute()) {
        echo "<p style='color:green'>Report sent successfully.</p>";
    } else {
        echo "<p style='color:red'>Error.</p>";
    }
}
?>

<br><a href="student_dashboard.php">Back to Dashboard</a>

</body>
</html>