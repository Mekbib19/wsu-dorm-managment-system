<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

/* Fetch blocks */
$stmt = $conn->prepare("SELECT id, block_number FROM blocks ORDER BY block_number");
$stmt->execute();
$blocks_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generate Student OTP</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="add.css">
</head>
<body>
<?php include 'sidebar.php'; ?>


<div class="container">
<div class="card">
    <h2>Generate Student OTP</h2>

    <!-- ✅ Admin feedback message -->
    <?php if (isset($_SESSION['otp_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['otp_message']); unset($_SESSION['otp_message']); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="generate_otp.php">
        <label>Student ID *</label>
        <input type="text" name="student_id" placeholder="UGR/.../.." required
               value="<?= htmlspecialchars($_COOKIE['last_student_id'] ?? '') ?>">

        <label>Assign Initial Block *</label>
        <select name="block_id" required>
            <option value="">-- Select Block --</option>
            <?php while ($b = $blocks_result->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= (isset($_COOKIE['last_block_id']) && $_COOKIE['last_block_id'] == $b['id']) ? 'selected' : '' ?>>
                    Block <?= htmlspecialchars($b['block_number']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit" name="generate_otp">Generate & Show OTP</button>
    </form>
</div>

<!-- Optional CSS for alert -->
<style>
.alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    font-weight: 500;
}
.alert-success {
    background: #ecfdf5;
    color: #065f46;
    border-left: 4px solid #10b981;
}
</style>

</div>

<?php if (isset($_SESSION['otp_data'])): ?>
<div id="otpModal" class="modal">
<div class="modal-content" id="printArea">

<h2 style="text-align:center;">Student OTP Slip</h2>
<hr>

<p><strong>Student ID:</strong> <?= htmlspecialchars($_SESSION['otp_data']['student_id']) ?></p>
<p><strong>Assigned Block:</strong> <?= htmlspecialchars($_SESSION['otp_data']['block']) ?></p>
<p><strong>OTP Code:</strong>
    <span style="font-size:24px;font-weight:bold;letter-spacing:4px;"><?= $_SESSION['otp_data']['otp'] ?></span>
</p>
<p><strong>Generated On:</strong> <?= $_SESSION['otp_data']['date'] ?></p>
<p style="color:#b91c1c;font-weight:600;">
    ⏱ OTP expires in <span id="otpCountdown">5:00</span>

<script>
let countdownTime = 300; // seconds
const countdownElement = document.getElementById('otpCountdown');
const countdownInterval = setInterval(() => {
    if (countdownTime <= 0) {
        clearInterval(countdownInterval);
        countdownElement.textContent = "00:00 (Expired)";
        return;
    }
    countdownTime--;
    const minutes = String(Math.floor(countdownTime / 60)).padStart(2, '0');
    const seconds = String(countdownTime % 60).padStart(2, '0');
    countdownElement.textContent = `${minutes}:${seconds}`;
}, 1000);


function closeModal() {
    document.getElementById('otpModal').style.display = 'none';
}
</script>


</script>

</script>

<hr>
<p style="text-align:center;font-size:13px;">Wolayita Sodo University — Dormitory Management System</p>

<div style="text-align:center;margin-top:20px;">
    <button onclick="window.print()">🖨 Print</button>
    <button onclick="closeModal()" style="background:#ef4444;">Close</button>
</div>

</div>
</div>
<?php unset($_SESSION['otp_data']); endif; ?>

<script>
function closeModal() { document.getElementById('otpModal').style.display='none'; }
document.addEventListener("DOMContentLoaded",()=>{
    const m=document.getElementById("otpModal");
    if(m) m.style.display="flex";
});
</script>

</body>
</html>
