<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = $_POST['from'];
    $subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $bodyFile = $_FILES['bodyfile']['tmp_name'];
    $emailCount = 0;

    if (!empty($from) && !empty($subject) && !empty($statusFilter) && is_uploaded_file($csvFile) && is_uploaded_file($bodyFile)) {
        $bodyTemplate = file_get_contents($bodyFile);
        if ($bodyTemplate === false) {
            echo "<p>Failed to read the body file.</p>";
            exit;
        }

        $handle = fopen($csvFile, 'r');
        if ($handle) {
            // Skip the header line
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                // Map CSV columns to variables
                [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                // Only send emails for rows with the selected status
                if (trim($status) === $statusFilter) {
                    // Replace placeholders in subject and body
                    $personalizedSubject = str_replace('[[FirstName]]', $firstName, $subject);
                    $personalizedBody = str_replace('[[FirstName]]', $firstName, $bodyTemplate);

                    // Send the email
                    $headers = "From: $from\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                        $emailCount++;
                    }

                    // Wait for 1 second before sending the next email
                    sleep(1);
                }
            }
            fclose($handle);

            echo "<p>Emails sent successfully: $emailCount</p>";
        } else {
            echo "<p>Failed to open the CSV file.</p>";
        }
    } else {
        echo "<p>Please fill in all fields and upload the required files.</p>";
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

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status" required>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <label for="csvfile">Upload CSV File:</label><br>
        <input type="file" name="csvfile" id="csvfile" accept=".csv" required><br><br>

        <label for="bodyfile">Upload Email Body (HTML File):</label><br>
        <input type="file" name="bodyfile" id="bodyfile" accept=".html" required><br><br>

        <button type="submit">Send Emails</button>
    </form>
</body>
</html>
