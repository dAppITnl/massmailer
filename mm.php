<?php
// Function to get available body files
function getBodyFiles($directory)
{
    $files = [];
    foreach (glob($directory . "/body_*.php") as $file) {
        $files[] = basename($file);
    }
    return $files;
}

// Load body files
$bodyFiles = getBodyFiles(__DIR__);
$subject = "";

// Dynamically update the subject based on the selected PHP file
if (isset($_GET['bodyfile'])) {
    $bodyFile = __DIR__ . '/' . basename($_GET['bodyfile']);
    if (file_exists($bodyFile)) {
        // Include the PHP file to execute it and capture the $subject variable
        ob_start();
        include $bodyFile;
        ob_end_clean();

        // Return the updated $subject variable
        header('Content-Type: text/plain');
        echo $subject;
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = "support.mis@checkCas.com";
    $email_subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $selectedBodyFile = $_POST['bodyfile'];
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (is_uploaded_file($csvFile) && !empty($selectedBodyFile)) {
            $bodyTemplate = file_get_contents(__DIR__ . "/" . $selectedBodyFile);
            if ($bodyTemplate === false) {
                echo "<p>Failed to read the body file.</p>";
                exit;
            }

            // Generate log file name
            $timestamp = date('dMy_Hi');
            $logFileName = $statusFilter . "_" . $timestamp . "_sent.txt";
            $logFilePath = __DIR__ . "/" . $logFileName;

            $handle = fopen($csvFile, 'r');
            if ($handle) {
                // Skip the header line
                fgetcsv($handle);

                while (($row = fgetcsv($handle)) !== false) {
                    // Map CSV columns to variables
                    // Sponsor,Campaign,First Name,Last Name,E-mail,Phone,Address,City,State,Zip,Status,Rating,IP,Date
                    //[$sponsor,$campaign,$firstName,$lastName,$email,$phone,$address,$city,$state,$zip,$status,$rating,$ip,$date] = $row;
                    // Username,First Name,Last Name,E-mail,Phone,Program,Status,Date Joined
                    [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                    // Check if the email should be skipped
                    if (in_array($email, $ignoredEmails)) {
                        continue;
                    }

                    // Only send emails for rows with the selected status
                    if (trim($status) === $statusFilter) {
                        // Replace placeholders in subject and body
                        $personalizedSubject = str_replace('[[FirstName]]', $firstName, $email_subject);
                        $personalizedBody = str_replace('[[FirstName]]', $firstName, $bodyTemplate);

                        // Send the email
                        $headers = "From: $from\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                            $emailCount++;
                            $sentEmails[] = $email; // Track the sent email
                        }

                        usleep(100000); // Sleep for 100,000 microseconds = 0.1 seconds
                    }
                }
                fclose($handle);

                // Save sent emails to a log file
                if (!empty($sentEmails)) {
                    file_put_contents($logFilePath, implode(PHP_EOL, $sentEmails));
                    echo "<p>Log file created: $logFilePath</p>";
                }

                echo "<p>Emails sent successfully: $emailCount</p>";
            } else {
                echo "<p>Failed to open the CSV file.</p>";
            }
        } else {
            echo "<p>Please upload the required CSV file and select a body file.</p>";
        }
    } else {
        echo "<p>Please select a status or leave it empty to send to all rows.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Email Sender</title>
    <style>
        iframe {
            width: 100%;
            height: 500px;
            border: 1px solid #ccc;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>CSV Email Sender</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <p>From: support.mis@checkCas.com</p>

        <!-- Subject Input -->
        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" value="<?= $subject; ?>" size="75"><br><br>

        <!-- Status Dropdown -->
        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">None</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <!-- CSV File Upload -->
        <label for="csvfile">Upload CSV File:</label><br>
        <input type="file" name="csvfile" id="csvfile" accept=".csv" required><br><br>

        <!-- Email Body File Dropdown -->
        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required onchange="updateSubject()">
            <option value="">-- Select Body File --</option>
            <?php foreach ($bodyFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <!-- Submit Button -->
        <button type="submit">Send Emails</button>
    </form>

    <!-- Iframe Preview -->
    <iframe id="bodyPreview" src=""></iframe>

    <script>
        function updateSubject() {
            const bodyFileSelect = document.getElementById('bodyfile');
            const selectedFile = bodyFileSelect.value;
            const iframe = document.getElementById('bodyPreview');

            if (selectedFile) {
                iframe.src = selectedFile; // Update iframe preview
                fetch(`?bodyfile=${encodeURIComponent(selectedFile)}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('subject').value = data; // Update subject field with plain text response
                    })
                    .catch(error => console.error('Error fetching subject:', error));
            } else {
                iframe.src = '';
            }
        }
    </script>
</body>
</html>
