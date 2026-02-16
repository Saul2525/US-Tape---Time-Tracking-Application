<?php require 'config.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Time Clock</title>
</head>
<body>
    <h2>Clock In / Out</h2>

    <form method="POST" action="clock_handler.php">
        Employee Email:<br>
        <input type="email" name="email" required><br><br>

        4-Digit PIN:<br>
        <input type="password" name="pin" maxlength="4" required><br><br>

        <input type="hidden" id="lat" name="lat">
        <input type="hidden" id="lng" name="lng">

        <button type="submit">Submit</button>
    </form>

<script>
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        document.getElementById('lat').value = position.coords.latitude;
        document.getElementById('lng').value = position.coords.longitude;
    });
}
</script>

</body>
</html>
