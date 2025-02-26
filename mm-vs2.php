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

// Define paths
$bodyFilesPath = __DIR__ . '/bodyfiles/';
$emailListsPath = __DIR__ . '/email-lists/';

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['csvfileUpload'])) {
        $filename = basename($_FILES['csvfileUpload']['name']);
        $uploadPath = $emailListsPath . $filename;
        if (move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $uploadPath)) {
            echo json_encode(['status' => 'success', 'message' => "CSV file uploaded successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Failed to upload CSV file."]);
        }
        exit;
    }

    if (isset($_FILES['bodyfileUpload'])) {
        $filename = basename($_FILES['bodyfileUpload']['name']);
        $uploadPath = $bodyFilesPath . $filename;
        if (move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $uploadPath)) {
            echo json_encode(['status' => 'success', 'message' => "Body file uploaded successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Failed to upload body file."]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'No file received.']);
    exit;
}

// API to get file lists
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
                    const csvSelect = document.getElementById('csvfile');
                    csvSelect.innerHTML = '<option value="">-- Select CSV File --</option>';
                    data.csvFiles.forEach(file => csvSelect.add(new Option(file, file)));

                    const bodySelect = document.getElementById('bodyfile');
                    bodySelect.innerHTML = '<option value="">-- Select Body File --</option>';
                    data.bodyFiles.forEach(file => bodySelect.add(new Option(file, file)));
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

        <label for="csvfile">Select CSV File:</label><br>
        <select name="csvfile" id="csvfile" required></select><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required></select><br><br>

        <button type="submit" name="sendEmails">Send Emails</button>
        <p id="emailMessage"></p>
    </form>

    <h2>Upload CSV File</h2>
    <form id="uploadCsvForm" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" accept=".csv" required>
        <button type="button" id="uploadCsvButton">Upload CSV</button>
        <p id="csvMessage"></p>
    </form>

    <h2>Upload Email Body File</h2>
    <form id="uploadBodyForm" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" accept=".php,.html" required>
        <button type="button" id="uploadBodyButton">Upload Body File</button>
        <p id="bodyMessage"></p>
    </form>

    <h2>Email Body Preview</h2>
    <iframe id="bodyIframe" style="width:90%; height:500px; border:1px solid #ccc;"></iframe>
</body>
</html>
