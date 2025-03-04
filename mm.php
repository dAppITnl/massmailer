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

// API to fetch body file content
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from = "support.mis@checkCas.com";
    $email_subject = $_POST['subject'];
    $statusFilter = $_POST['status'];
    $startDate = $_POST['startDate'];
    $selectedBodyFile = $_POST['bodyfile'];
    $csvFile = $_POST['csvfile'];
    $emailCount = 0;
    $sentEmails = [];

    if (!empty($statusFilter) && !empty($email_subject)) {
        if (!empty($csvFile) && !empty($selectedBodyFile)) {
            $bodyTemplate = file_get_contents($bodyFilesPath . $selectedBodyFile);
            if ($bodyTemplate === false) {
                echo "<p>Failed to read the body file.</p>";
                exit;
            }

            // Generate log file name
            $timestamp = date('dMy_Hi');
            $logFileName = $statusFilter . "_" . $timestamp . "_sent.txt";
            $logFilePath = __DIR__ . "/" . $logFileName;

            $handle = fopen($emailListsPath . $csvFile, 'r');
            if ($handle) {
                // Skip the header line
                fgetcsv($handle);

                while (($row = fgetcsv($handle)) !== false) {
                    // Map CSV columns to variables
                    // Sponsor,Campaign,First Name,Last Name,E-mail,Phone,Address,City,State,Zip,Status,Rating,IP,Date
                    [$sponsor,$campaign,$firstName,$lastName,$email,$phone,$address,$city,$state,$zip,$status,$rating,$ip,$dateJoined] = $row;
                    // Username,First Name,Last Name,E-mail,Phone,Program,Status,Date Joined
                    //[$username, $firstName, $lastName, $email, $phone, $program, $status, $dateJoined] = $row;

                    // Check if the email should be skipped
                    if (in_array($email, $ignoredEmails)) {
                        continue;
                    }

                    if (strtotime($dateJoined) >= strtotime($startDate)) {
                        continue;
                    }

                    // Only send emails for rows with the selected status
                    if (trim($status) === $statusFilter) {
                        // Replace placeholders in subject and body
                        $personalizedSubject = str_replace('[[FirstName]]', $firstName, $email_subject) . " 🌟";
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

            select.innerHTML = '<option value="">-- Select a File --</option>';
            files.forEach(file => {
                const option = document.createElement("option");
                option.value = file;
                option.textContent = file;
                select.appendChild(option);
            });
        }

        function uploadFile(formData, messageElement) {
            messageElement.innerHTML = "Processing...";
            messageElement.style.color = "blue";

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                messageElement.innerHTML = result.message;
                messageElement.style.color = result.status === 'success' ? 'green' : 'red';
                if (result.status === 'success') {
                    refreshFileLists();
                }
            })
            .catch(error => {
                messageElement.innerHTML = 'Error uploading file.';
                messageElement.style.color = 'red';
                console.error('Upload error:', error);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            refreshFileLists();

            document.getElementById('uploadCsvButton').addEventListener('click', function() {
                let formData = new FormData(document.getElementById('uploadCsvForm'));
                uploadFile(formData, document.getElementById('csvMessage'));
            });

            document.getElementById('uploadBodyButton').addEventListener('click', function() {
                let formData = new FormData(document.getElementById('uploadBodyForm'));
                uploadFile(formData, document.getElementById('bodyMessage'));
            });

            document.getElementById('bodyfile').addEventListener('change', function() {
                let selectedFile = this.value;
                if (selectedFile) {
                    fetch(`?getBodyFile=1&file=${encodeURIComponent(selectedFile)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === "success") {
                                document.getElementById("bodyPreview").innerHTML = data.content;
                                document.getElementById("subject").value = data.subject;
                            } else {
                                document.getElementById("bodyPreview").innerHTML = "<p style='color:red;'>Error loading file.</p>";
                            }
                        })
                        .catch(error => console.error("Error loading body file:", error));
                } else {
                    document.getElementById("bodyPreview").innerHTML = "";
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
    <form id="uploadCsvForm" method="post" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" required>
        <button type="button" id="uploadCsvButton">Upload CSV</button>
        <p id="csvMessage"></p>
    </form>

    <h2>Upload Email Body File</h2>
    <form id="uploadBodyForm" method="post" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" required>
        <button type="button" id="uploadBodyButton">Upload Body</button>
        <p id="bodyMessage"></p>
    </form>

    <h2>Email Body Preview</h2>
    <div id="bodyPreview" style="border:1px solid #ccc; padding:10px;"></div>
</body>
</html>
