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

// API to get file lists
if (isset($_GET['getFiles'])) {
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
    $csvFile = $_FILES['csvfile']['tmp_name'];
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (is_uploaded_file($csvFile) && !empty($selectedBodyFile)) {
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
                    [$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                    if (in_array($email, $ignoredEmails)) continue;
                    if (trim($status) === $statusFilter) {
                        $personalizedSubject = str_replace('[[FirstName]]', $firstName, $email_subject) . " 🌟";
                        $personalizedBody = str_replace('[[FirstName]]', $firstName, $bodyTemplate);

                        $headers = "From: $from\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        if (mail($email, $personalizedSubject, $personalizedBody, $headers)) {
                            $emailCount++;
                            $sentEmails[] = $email;
                        }
                        usleep(100000);
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
        function refreshFileLists() {
            fetch('?getFiles=true')
                .then(response => response.json())
                .then(data => {
                    const csvSelect = document.getElementById('csvfile');
                    if (csvSelect.tagName === 'SELECT') {
                        csvSelect.innerHTML = '<option value="">-- Select CSV File --</option>';
                        data.csvFiles.forEach(file => csvSelect.add(new Option(file, file)));
                    }

                    const bodySelect = document.getElementById('bodyfile');
                    bodySelect.innerHTML = '<option value="">-- Select Body File --</option>';
                    data.bodyFiles.forEach(file => bodySelect.add(new Option(file, file)));
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            refreshFileLists();

            document.getElementById('bodyfile').addEventListener('change', function() {
                let selectedFile = this.value;
                if (selectedFile) {
                    fetch(`?getBodyFile=true&file=${encodeURIComponent(selectedFile)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                document.getElementById('subject').value = data.subject;
                                document.getElementById('bodyPreview').innerHTML = data.content;
                            } else {
                                document.getElementById('bodyPreview').innerHTML = 'Error loading file.';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching body file:', error);
                            document.getElementById('bodyPreview').innerHTML = 'Error fetching file.';
                        });
                } else {
                    document.getElementById('bodyPreview').innerHTML = '';
                    document.getElementById('subject').value = '';
                }
            });
        });
    </script>
</head>
<body>
    <h1>CSV Email Sender</h1>

    <form id="emailForm" action="" method="post" enctype="multipart/form-data">
        <label for="from">From:</label><br>
        <input type="email" id="from" name="from" value="support.mis@checkCas.com" size="50" readonly><br><br>

        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" size="75" required><br><br>

        <label for="csvfile">Select CSV File:</label><br>
        <input type="file" name="csvfile" id="csvfile" required><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required></select><br><br>

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">None</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <button type="submit" name="sendEmails">Send Emails</button>
        <p id="emailMessage"></p>
    </form>

    <h2>Email Body Preview</h2>
    <div id="bodyPreview" style="width:90%; min-height:300px; padding:10px; border:1px solid #ccc; background:#f9f9f9;"></div>
</body>
</html>
