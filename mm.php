<?php
// Function to get available files from a directory
function getFiles($directory, $pattern)
{
    $files = [];
    foreach (glob($directory . $pattern) as $file) {
        $files[] = basename($file);
    }
    return $files;
}

// Define paths
$bodyFilesPath = __DIR__ . '/bodyfiles/';
$emailListsPath = __DIR__ . '/email-lists/';

// Load available body files and email list files
$bodyFiles = getFiles($bodyFilesPath, "body_*.php");
$emailLists = getFiles($emailListsPath, "*.csv");

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

// Handle CSV file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uploadCsvFile'])) {
    if (isset($_FILES['csvfileUpload']) && $_FILES['csvfileUpload']['error'] == 0) {
        $uploadPath = $emailListsPath . basename($_FILES['csvfileUpload']['name']);
        if (move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $uploadPath)) {
            echo "<p>CSV file uploaded successfully.</p>";
        } else {
            echo "<p>Failed to upload CSV file.</p>";
        }
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmails'])) {
    $from = trim($_POST['from']) ?: "support.mis@checkCas.com";
    $email_subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $selectedBodyFile = $_POST['bodyfile'];
    $selectedCsvFile = $_POST['csvfile'];
    $startDate = $_POST['startDate'] . ' 00:00:00';
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (!empty($selectedCsvFile) && !empty($selectedBodyFile)) {
            $csvFilePath = $emailListsPath . $selectedCsvFile;
            $bodyTemplate = file_get_contents($bodyFilesPath . $selectedBodyFile);
            if ($bodyTemplate === false) {
                echo "<p>Failed to read the body file.</p>";
                exit;
            }

            $timestamp = date('dMy_Hi');
            $logFileName = $statusFilter . "_" . $timestamp . "_sent.txt";
            $logFilePath = __DIR__ . "/" . $logFileName;

            $handle = fopen($csvFilePath, 'r');
            if ($handle) {
                fgetcsv($handle); // Skip header

                while (($row = fgetcsv($handle)) !== false) {
                    // Updated column mapping
                    [
                        $sponsor, $campaign, $firstName, $lastName, $email, $phone, $address, 
                        $city, $state, $zip, $status, $rating, $ip, $date
                    ] = $row;
                
                    // Convert date format to compare
                    $date = date('Y-m-d H:i:s', strtotime($date));
                
                    // Skip emails if Date is newer than startDate
                    if ($date >= $startDate) {
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
            echo "<p>Please select a CSV file and a body file.</p>";
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
</head>
<body>
    <h1>CSV Email Sender</h1>
    
    <form action="" method="post">
        <label for="from">From:</label><br>
        <input type="email" id="from" name="from" value="support.mis@checkCas.com" size="50"><br><br>

        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" value="<?= $subject; ?>" size="75"><br><br>

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">None</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <label for="startDate">Ignore rows where Date is on or after:</label><br>
        <input type="date" id="startDate" name="startDate" value="<?= $defaultStartDate; ?>"><br><br>

        <label for="csvfile">Select CSV File:</label><br>
        <select name="csvfile" id="csvfile" required>
            <option value="">-- Select CSV File --</option>
            <?php foreach ($emailLists as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required>
            <option value="">-- Select Body File --</option>
            <?php foreach ($bodyFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit" name="sendEmails">Send Emails</button>
    </form>

    <h2>Upload CSV File</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" accept=".csv">
        <button type="submit" name="uploadCsvFile">Upload CSV</button>
    </form>

    <h2>Upload Email Body File</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" accept=".php">
        <button type="submit" name="uploadBodyFile">Upload Body File</button>
    </form>
</body>
</html>
