<?php
if(isset($_POST['password'])){
    echo password_hash($_POST['password'],PASSWORD_DEFAULT);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="" method="post">
     <input type="text" name="password" id="">
     <input type="submit" value="Password Generetor">
    </form>
</body>
</html>