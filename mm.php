<?php
// Function to get available body files
function getBodyFiles($directory)
{
    $files = [];
    foreach (glob($directory . "/body_*.html") as $file) {
        $files[] = basename($file);
    }
    return $files;
}

// Function to read ignored emails
function getIgnoredEmails($file)
{
    if (file_exists($file)) {
        return array_map('trim', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
    return [];
}

$bodyFiles = getBodyFiles(__DIR__);
$ignoreFile = __DIR__ . "/sent_ignore.txt";
$ignoredEmails = getIgnoredEmails($ignoreFile);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = "support.mis@checkCas.com";
    $subject = "[[FirstName]], as PHG-member: join the 'Multi Income Streams' Facebook Group! 🌟";
    $statusFilter = $_POST['status'];
    $selectedBodyFile = $_POST['bodyfile'];
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) || $statusFilter === "") {
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
                    [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                    // Check if the email should be skipped
                    if (in_array($email, $ignoredEmails)) {
                        continue;
                    }

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
                            $sentEmails[] = $email; // Track the sent email
                        }

                        // Wait for 1 second before sending the next email
                        sleep(1);
                    }
                }
                fclose($handle);

                // Save sent emails to a log file
                if (!empty($sentEmails)) {
                    file_put_contents($logFilePath, implode(PHP_EOL, $sentEmails));
                }

                echo "<p>Emails sent successfully: $emailCount</p>";
                echo "<p>Log file created: $logFileName</p>";
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
        <p><strong>From:</strong> support.mis@checkCas.com (fixed)</p>
        <p><strong>Subject:</strong> [[FirstName]], as PHG-member: join the 'Multi Income Streams' Facebook Group! 🌟 (fixed)</p>

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">All</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <label for="csvfile">Upload CSV File:</label><br>
        <input type="file" name="csvfile" id="csvfile" accept=".csv" required><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required onchange="updateIframePreview()">
            <option value="">-- Select Body File --</option>
            <?php foreach ($bodyFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit">Send Emails</button>
    </form>

    <iframe id="bodyPreview" src=""></iframe>

    <script>
        function updateIframePreview() {
            const bodyFileSelect = document.getElementById('bodyfile');
            const selectedFile = bodyFileSelect.value;
            const iframe = document.getElementById('bodyPreview');

            if (selectedFile) {
                iframe.src = selectedFile;
            } else {
                iframe.src = '';
            }
        }
    </script>
</body>
</html>
