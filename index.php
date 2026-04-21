<?php require 'config.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Clock In / Out</title>
</head>
<body style="text-align:center; font-family: Arial; margin-top:100px;">

    <h1>Clock In / Out</h1>

    <form method="POST" action="clock_handler.php">

        <label style="font-size:20px;">Enter 5-Digit PIN</label><br><br>

        <input 
            type="text"
            name="pin"
            maxlength="5"
            pattern="\d{5}"
            inputmode="numeric"
            required
            style="font-size:30px; text-align:center; width:150px;"
        >

        <br><br>

        <button 
            type="submit"
            style="padding:15px 40px; font-size:18px;"
        >
            Submit
        </button>

    </form>

    <br><br><br>

<a href="manager_login.php">
    <button style="
        padding:8px 20px;
        font-size:14px;
        background:#333;
        color:white;
        border:none;
        cursor:pointer;
    ">
        Manager Sign In
    </button>
</a>

</body>
</html>