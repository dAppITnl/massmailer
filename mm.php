<?php
// Function to get available files
function getFiles($directory, $pattern)
{
    $files = [];
    foreach (glob($directory . $pattern) as $file) {
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

// Define paths
$bodyFilesPath = __DIR__ . '/bodyfiles/';
$emailListsPath = __DIR__ . '/email-lists/';
$ignoreFile = __DIR__ . "/sent_ignore.txt";

// Handle CSV file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csvfileUpload'])) {
    $filename = basename($_FILES['csvfileUpload']['name']);
    $uploadPath = $emailListsPath . $filename;
    if (move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $uploadPath)) {
        echo json_encode(['status' => 'success', 'message' => "CSV file uploaded successfully."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Failed to upload CSV file."]);
    }
    exit;
}

// Handle body file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bodyfileUpload'])) {
    $filename = basename($_FILES['bodyfileUpload']['name']);
    $uploadPath = $bodyFilesPath . $filename;
    if (move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $uploadPath)) {
        echo json_encode(['status' => 'success', 'message' => "Body file uploaded successfully."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Failed to upload body file."]);
    }
    exit;
}

// API to get file lists
if (isset($_GET['getFiles'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'csvFiles' => getFiles($emailListsPath, "*.csv"),
        'bodyFiles' => getFiles($bodyFilesPath, "body_*.php")
    ]);
    exit;
}

// API to fetch and process the selected body file
if (isset($_GET['getBodyFile']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = $bodyFilesPath . $file;

    if (file_exists($filePath)) {
        ob_start();
        include $filePath;
        $content = ob_get_clean();
        $subject = isset($subject) ? $subject : '';

        echo json_encode([
            'status' => 'success',
            'subject' => $subject,
            'content' => $content
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found.']);
    }
    exit;
}

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmails'])) {
    $from = "support.mis@checkCas.com";
    $email_subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $selectedBodyFile = $_POST['bodyfile'];
    $csvFile = $emailListsPath . $_POST['csvfile'];
    $startDate = $_POST['startDate']; // Get the start date from the form
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (file_exists($csvFile) && !empty($selectedBodyFile)) {
            $bodyTemplate = file_get_contents($bodyFilesPath . $selectedBodyFile);
            if ($bodyTemplate === false) {
                echo "<p>Failed to read the body file.</p>";
                exit;
            }

            $ignoredEmails = getIgnoredEmails($ignoreFile);
            $timestamp = date('dMy_Hi');
            $logFileName = $statusFilter . "_" . $timestamp . "_sent.txt";
            $logFilePath = __DIR__ . "/" . $logFileName;

            $handle = fopen($csvFile, 'r');
            if ($handle) {
                fgetcsv($handle); // Skip the header line

                while (($row = fgetcsv($handle)) !== false) {
                    [$sponsor, $campaign, $firstName, $lastName, $email, $phone, $address, $city, $state, $zip, $status, $rating, $ip, $dateJoined] = $row;

                    // Convert CSV date to comparable format
                    $dateJoinedFormatted = date('Y-m-d', strtotime($dateJoined));

                    // Filter by ignored emails and date
                    if (in_array($email, $ignoredEmails)) continue;
                    if (trim($status) !== $statusFilter) continue;
                    if ($dateJoinedFormatted >= $startDate) continue; // Skip if dateJoined is on or after startDate

                    // Personalize email
                    $personalizedSubject = str_replace('[[FirstName]]', $firstName, $email_subject) . " 🌟";
                    $personalizedBody = str_replace('[[FirstName]]', $firstName, $bodyTemplate);

                    // Send email
                    $headers = "From: $from\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                        $emailCount++;
                        $sentEmails[] = $email;
                    }
                    usleep(100000);
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
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            refreshFileLists();
        });

        function refreshFileLists() {
            fetch('?getFiles=true')
                .then(response => response.json())
                .then(data => {
                    if (data.csvFiles && data.bodyFiles) {
                        populateSelect("csvfile", data.csvFiles);
                        populateSelect("bodyfile", data.bodyFiles);
                    } else {
                        console.error("Invalid data received:", data);
                    }
                })
                .catch(error => console.error("Error fetching files:", error));
        }

        function populateSelect(selectId, files) {
            const select = document.getElementById(selectId);
            if (!select) return;

            select.innerHTML = '<option value="">-- Select a File --</option>'; // Clear existing options
            files.forEach(file => {
                const option = document.createElement("option");
                option.value = file;
                option.textContent = file;
                select.appendChild(option);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('bodyfile').addEventListener('change', function() {
                let selectedFile = this.value;
                if (selectedFile) {
                    document.getElementById('bodyIframe').src = 'bodyfiles/' + encodeURIComponent(selectedFile);
                } else {
                    document.getElementById('bodyIframe').src = ''; // Clear iframe when no file is selected
                }
            });
        });
    </script>
</head>
<body>
    <h1>CSV Email Sender</h1>

    <form id="emailForm" action="" method="post">
        <label>Subject:</label><br>
        <input type="text" id="subject" name="subject" size="75" required><br><br>

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">None</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <label for="startDate">Ignore rows where Date is on or after:</label><br>
        <input type="date" id="startDate" name="startDate" value="<?= date('Y-m-d', strtotime('-1 month')); ?>"><br><br>

        <label>Select CSV File:</label><br>
        <select name="csvfile" id="csvfile" required></select><br><br>

        <label>Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required></select><br><br>

        <button type="submit" name="sendEmails">Send Emails</button>
    </form>

    <h2>Upload CSV File</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" required>
        <button type="submit">Upload CSV</button>
    </form>

    <h2>Upload Email Body File</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" required>
        <button type="submit">Upload Body</button>
    </form>

    <h2>Email Body Preview</h2>
    <div id="bodyPreview" style="border:1px solid #ccc; padding:10px;"></div>
</body>
</html>
