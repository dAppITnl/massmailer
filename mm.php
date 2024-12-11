<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = $_POST['from'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $emailCount = 0;

    if (!empty($from) && !empty($subject) && !empty($body) && is_uploaded_file($csvFile)) {
        $handle = fopen($csvFile, 'r');
        if ($handle) {
            // Skip the header line
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                // Map CSV columns to variables
                [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                // Replace placeholders in subject and body
                $personalizedSubject = str_replace('[[FirstName]]', $firstName, $subject);
                $personalizedBody = str_replace('[[FirstName]]', $firstName, $body);

                // Send the email
                $headers = "From: $from\r\n";
                if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                    $emailCount++;
                }
            }
            fclose($handle);

            echo "<p>Emails sent successfully: $emailCount</p>";
        } else {
            echo "<p>Failed to open the CSV file.</p>";
        }
    } else {
        echo "<p>Please fill in all fields and upload a valid CSV file.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Email Sender</title>
</head>
<body>
    <h1>CSV Email Sender</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="from">From:</label><br>
        <input type="email" name="from" id="from" required><br><br>

        <label for="subject">Subject:</label><br>
        <input type="text" name="subject" id="subject" required><br><br>

        <label for="body">Body:</label><br>
        <textarea name="body" id="body" rows="5" required></textarea><br><br>

        <label for="csvfile">Upload CSV File:</label><br>
        <input type="file" name="csvfile" id="csvfile" accept=".csv" required><br><br>

        <button type="submit">Send Emails</button>
    </form>
</body>
</html>
