<?php
// Function to get available body files
function getBodyFiles($directory)
{
    $files = [];
    foreach (glob($directory . "body_*.php") as $file) {
        $files[] = basename($file);
    }
    return $files;
}

// Load body files
$bodyFilesPath = __DIR__ . '/bodyfiles/';
$bodyFiles = getBodyFiles($bodyFilesPath);
$subject = "";

// Set default start date to one month ago
$defaultStartDate = date('Y-m-d', strtotime('-1 month'));

// Dynamically update the subject based on the selected PHP file
if (isset($_GET['bodyfile'])) {
    $bodyFile = $bodyFilesPath . basename($_GET['bodyfile']);
    if (file_exists($bodyFile)) {
        ob_start();
        include $bodyFile;
        ob_end_clean();
        header('Content-Type: text/plain');
        echo $subject;
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmails'])) {
    $from = trim($_POST['from']) ?: "support.mis@checkCas.com";
    $email_subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $selectedBodyFile = $_POST['bodyfile'];
    $startDate = $_POST['startDate'] . ' 00:00:00';
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (is_uploaded_file($csvFile) && !empty($selectedBodyFile)) {
            $bodyTemplate = file_get_contents(__DIR__ . "/bodyfiles/" . $selectedBodyFile);
            if ($bodyTemplate === false) {
                echo "<p>Failed to read the body file.</p>";
                exit;
            }

            $timestamp = date('dMy_Hi');
            $logFileName = $statusFilter . "_" . $timestamp . "_sent.txt";
            $logFilePath = __DIR__ . "/" . $logFileName;

            $handle = fopen($csvFile, 'r');
            if ($handle) {
                fgetcsv($handle); // Skip header

                while (($row = fgetcsv($handle)) !== false) {
                    [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                    // Convert date format to compare
                    $dateJoined = date('Y-m-d H:i:s', strtotime($dateJoined));

                    // Skip emails if dateJoined is newer than startDate
                    if ($dateJoined >= $startDate) {
                        continue;
                    }

                    if (trim($status) === $statusFilter) {
                        $personalizedSubject = str_replace('[[FirstName]]', $firstName, $email_subject);
                        $personalizedBody = str_replace('[[FirstName]]', $firstName, $bodyTemplate);

                        $headers = "From: $from\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                            $emailCount++;
                            $sentEmails[] = $email;
                        }

                        usleep(100000); // Sleep for 0.1s
                    }
                }
                fclose($handle);

                if (!empty($sentEmails)) {
                    file_put_contents($logFilePath, implode(PHP_EOL, $sentEmails));
                    echo "<p>Log file created: $logFilePath</p>";
                }

                echo "<p>Emails sent successfully: $emailCount</p>";
            } else {
                echo "<p>Failed to open the CSV file.</p>";
            }
        } else {
            echo "<p>Please upload a CSV file and select a body file.</p>";
        }
    } else {
        echo "<p>Please select a status or leave it empty to send to all rows.</p>";
    }
}

// Handle body file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uploadBodyFile'])) {
    if (isset($_FILES['bodyfileUpload']) && $_FILES['bodyfileUpload']['error'] == 0) {
        $uploadPath = $bodyFilesPath . basename($_FILES['bodyfileUpload']['name']);
        if (move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $uploadPath)) {
            echo "<p>Body file uploaded successfully.</p>";
        } else {
            echo "<p>Failed to upload body file.</p>";
        }
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
        iframe { width: 100%; height: 500px; border: 1px solid #ccc; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>CSV Email Sender</h1>
    
    <form action="" method="post" enctype="multipart/form-data">
        <!-- From Input -->
        <label for="from">From:</label><br>
        <input type="email" id="from" name="from" value="support.mis@checkCas.com" size="50"><br><br>

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

        <!-- Start Date Input -->
        <label for="startDate">Ignore rows where Date is on or after:</label><br>
        <input type="date" id="startDate" name="startDate" value="<?= $defaultStartDate; ?>"><br><br>

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
        <button type="submit" name="sendEmails">Send Emails</button>
    </form>

    <h2>Upload Email Body File</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="bodyfileUpload">Upload PHP Email Body File:</label><br>
        <input type="file" name="bodyfileUpload" id="bodyfileUpload" accept=".php"><br><br>
        <button type="submit" name="uploadBodyFile">Upload</button>
    </form>

    <!-- Iframe Preview -->
    <iframe id="bodyPreview" src=""></iframe>

    <script>
        function updateSubject() {
            const bodyFileSelect = document.getElementById('bodyfile');
            const selectedFile = bodyFileSelect.value;
            const iframe = document.getElementById('bodyPreview');

            if (selectedFile) {
                iframe.src = 'bodyfiles/' + selectedFile; 
                fetch(`?bodyfile=${encodeURIComponent(selectedFile)}`)
                    .then(response => response.text())
                    .then(data => document.getElementById('subject').value = data)
                    .catch(error => console.error('Error fetching subject:', error));
            } else {
                iframe.src = '';
            }
        }
    </script>
</body>
</html>
