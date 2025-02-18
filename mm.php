<?php
// Prevent accidental whitespace before output
ob_start(); // Start output buffering
header('Content-Type: application/json'); // Ensure JSON response format
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get available files
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

// Handle CSV file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uploadCsvFile'])) {
    if (!isset($_FILES['csvfileUpload'])) {
        echo json_encode(['status' => 'error', 'message' => 'No file received.']);
        exit;
    }

    $fileError = $_FILES['csvfileUpload']['error'];
    if ($fileError !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.",
            UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload."
        ];
        echo json_encode(['status' => 'error', 'message' => $errorMessages[$fileError] ?? "Unknown upload error."]);
        exit;
    }

    // Ensure the upload directory exists
    if (!is_dir($emailListsPath)) {
        mkdir($emailListsPath, 0777, true);
    }

    $uploadPath = $emailListsPath . basename($_FILES['csvfileUpload']['name']);
    if (move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $uploadPath)) {
        echo json_encode(['status' => 'success', 'message' => 'CSV file uploaded successfully (overwritten if existed).']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check folder permissions.']);
    }
    exit;
}

// Handle body file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uploadBodyFile'])) {
    if (!isset($_FILES['bodyfileUpload'])) {
        echo json_encode(['status' => 'error', 'message' => 'No file received.']);
        exit;
    }

    $uploadPath = $bodyFilesPath . basename($_FILES['bodyfileUpload']['name']);
    if (move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $uploadPath)) {
        echo json_encode(['status' => 'success', 'message' => 'Body file uploaded successfully (overwritten if existed).']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload body file.']);
    }
    exit;
}

// API endpoint to get file lists
if (isset($_GET['getFiles'])) {
    echo json_encode([
        'csvFiles' => getFiles($emailListsPath, "*.csv"),
        'bodyFiles' => getFiles($bodyFilesPath, "body_*.php")
    ]);
    exit;
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
                    // Update CSV select
                    const csvSelect = document.getElementById('csvfile');
                    csvSelect.innerHTML = '<option value="">-- Select CSV File --</option>';
                    data.csvFiles.forEach(file => {
                        let option = new Option(file, file);
                        csvSelect.add(option);
                    });

                    // Update Body File select
                    const bodySelect = document.getElementById('bodyfile');
                    bodySelect.innerHTML = '<option value="">-- Select Body File --</option>';
                    data.bodyFiles.forEach(file => {
                        let option = new Option(file, file);
                        bodySelect.add(option);
                    });
                });
        }

        function uploadFile(formData, messageElement) {
            messageElement.innerHTML = "Processing..."; // Show processing message
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
                    refreshFileLists(); // Refresh file dropdowns after successful upload
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

            function handleFormSubmit(event, messageElement) {
                event.preventDefault();
                messageElement.innerHTML = ""; // Clear previous message
                let formData = new FormData(event.target);
                uploadFile(formData, messageElement);
            }

            document.getElementById('uploadCsvForm').addEventListener('submit', function(event) {
                handleFormSubmit(event, document.getElementById('csvMessage'));
            });

            document.getElementById('uploadBodyForm').addEventListener('submit', function(event) {
                handleFormSubmit(event, document.getElementById('bodyMessage'));
            });

            document.getElementById('emailForm').addEventListener('submit', function() {
                document.getElementById('emailMessage').innerHTML = "Processing...";
                document.getElementById('emailMessage').style.color = "blue";
            });
        });
    </script>
</head>
<body>
    <h1>CSV Email Sender</h1>

    <form id="emailForm" action="" method="post">
        <label for="from">From:</label><br>
        <input type="email" id="from" name="from" value="support.mis@checkCas.com" size="50"><br><br>

        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" size="75"><br><br>

        <label for="status">Send Emails to Status:</label><br>
        <select name="status" id="status">
            <option value="">None</option>
            <option value="Unpaid">Unpaid</option>
            <option value="Active">Active</option>
        </select><br><br>

        <label for="startDate">Ignore rows where Date is on or after:</label><br>
        <input type="date" id="startDate" name="startDate" value="<?= date('Y-m-d', strtotime('-1 month')); ?>"><br><br>

        <label for="csvfile">Select CSV File:</label><br>
        <select name="csvfile" id="csvfile" required></select><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required></select><br><br>

        <button type="submit" name="sendEmails">Send Emails</button>
        <p id="emailMessage"></p> <!-- Status message for email form -->
    </form>

    <h2>Upload CSV File</h2>
    <form id="uploadCsvForm" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" accept=".csv" required>
        <button type="submit" name="uploadCsvFile">Upload CSV</button>
        <p id="csvMessage"></p> <!-- Status message for CSV upload -->
    </form>

    <h2>Upload Email Body File</h2>
    <form id="uploadBodyForm" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" accept=".php" required>
        <button type="submit" name="uploadBodyFile">Upload Body File</button>
        <p id="bodyMessage"></p> <!-- Status message for body file upload -->
    </form>
</body>
</html>
