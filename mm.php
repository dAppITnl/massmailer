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

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['uploadCsvFile']) && isset($_FILES['csvfileUpload']) && $_FILES['csvfileUpload']['error'] == 0) {
        move_uploaded_file($_FILES['csvfileUpload']['tmp_name'], $emailListsPath . basename($_FILES['csvfileUpload']['name']));
        echo json_encode(['status' => 'success', 'message' => 'CSV file uploaded successfully.']);
        exit;
    }

    if (isset($_POST['uploadBodyFile']) && isset($_FILES['bodyfileUpload']) && $_FILES['bodyfileUpload']['error'] == 0) {
        move_uploaded_file($_FILES['bodyfileUpload']['tmp_name'], $bodyFilesPath . basename($_FILES['bodyfileUpload']['name']));
        echo json_encode(['status' => 'success', 'message' => 'Body file uploaded successfully.']);
        exit;
    }
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
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                messageElement.innerHTML = result.message;
                refreshFileLists(); // Refresh the selects
            })
            .catch(error => console.error('Error uploading file:', error));
        }

        document.addEventListener('DOMContentLoaded', () => {
            refreshFileLists();

            document.getElementById('uploadCsvForm').addEventListener('submit', function(event) {
                event.preventDefault();
                let formData = new FormData(this);
                uploadFile(formData, document.getElementById('csvMessage'));
            });

            document.getElementById('uploadBodyForm').addEventListener('submit', function(event) {
                event.preventDefault();
                let formData = new FormData(this);
                uploadFile(formData, document.getElementById('bodyMessage'));
            });
        });
    </script>
</head>
<body>
    <h1>CSV Email Sender</h1>

    <form action="" method="post">
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
    </form>

    <h2>Upload CSV File</h2>
    <form id="uploadCsvForm" enctype="multipart/form-data">
        <input type="file" name="csvfileUpload" accept=".csv" required>
        <button type="submit" name="uploadCsvFile">Upload CSV</button>
        <p id="csvMessage"></p>
    </form>

    <h2>Upload Email Body File</h2>
    <form id="uploadBodyForm" enctype="multipart/form-data">
        <input type="file" name="bodyfileUpload" accept=".php" required>
        <button type="submit" name="uploadBodyFile">Upload Body File</button>
        <p id="bodyMessage"></p>
    </form>
</body>
</html>
