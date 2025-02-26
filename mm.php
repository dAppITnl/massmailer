<?php
// Prevent accidental whitespace before output
// header('Content-Type: application/json'); 

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

// Handle CSV file and body file uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['csvfileUpload'])) {
        $filename = basename($_FILES['csvfileUpload']['name']);
        $uploadPath = $emailListsPath . $filename;
        if (move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $uploadPath)) {
            echo json_encode(['status' => 'success', 'message' => "CSV file '".$filename."' uploaded successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Failed to upload CSV file: '".$filename."'."]);
        }
        exit;
    }

    if (isset($_FILES['bodyfileUpload'])) {
        $filename = basename($_FILES['bodyfileUpload']['name']);
        $uploadPath = $bodyFilesPath . $filename;
        if (move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $uploadPath)) {
            echo json_encode(['status' => 'success', 'message' => "Body file '".$filename."' uploaded successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Failed to upload body file: '".$filename."'."]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'No file received.']);
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

// API endpoint to get the content of a selected body file
if (isset($_GET['getBodyContent']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = $bodyFilesPath . $file;

    if (file_exists($filePath)) {
        echo json_encode(['status' => 'success', 'content' => file_get_contents($filePath)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found.']);
    }
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

            document.getElementById('emailForm').addEventListener('submit', function(event) {
                event.preventDefault();
                document.getElementById('emailMessage').innerHTML = "Processing...";
                document.getElementById('emailMessage').style.color = "blue";
                this.submit();
            });

            // Event listener for body file selection
            document.getElementById('bodyfile').addEventListener('change', function() {
                let selectedFile = this.value;
                if (selectedFile) {
                    fetch(`?getBodyContent=true&file=${encodeURIComponent(selectedFile)}`)
                        .then(response => response.json())
                        .then(data => {
                            const bodyContentArea = document.getElementById('bodyContent');
                            if (data.status === 'success') {
                                console.log('Body content:', data.content);
                                bodyContentArea.value = data.content;
                            } else {
                                console.error('Error loading file content:', data.message);
                                bodyContentArea.value = 'Error loading file content.';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching file content:', error);
                            document.getElementById('bodyContent').value = 'Error fetching file content.';
                        });
                } else {
                    console.log('No file selected.');
                    document.getElementById('bodyContent').value = ''; // Clear when no file is selected
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

        <label for="bodyContent">Email Body Preview:</label><br>
        <textarea id="bodyContent" rows="10" cols="80" readonly></textarea><br><br>

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
        <input type="file" name="bodyfileUpload" accept=".php" required>
        <button type="button" id="uploadBodyButton">Upload Body File</button>
        <p id="bodyMessage"></p>
    </form>
</body>
</html>
