<?php
// Function to get available body files
function getBodyFiles($directory)
{
    $files = [];
    foreach (glob($directory . "/body_*.php") as $file) {
        $files[] = basename($file);
    }
    return $files;
}

// Load body files
$bodyFiles = getBodyFiles(__DIR__);
$subject = "[[FirstName]], as PHG-member: join the 'Multi Income Streams' Facebook Group!";

// Dynamically update the subject based on the selected PHP file
if (isset($_GET['bodyfile'])) {
    $bodyFile = __DIR__ . '/' . basename($_GET['bodyfile']);
    if (file_exists($bodyFile)) {
        include $bodyFile; // Load the file to set the $subject
        echo json_encode(['subject' => $subject]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle the form submission
    $selectedBodyFile = $_POST['bodyfile'];
    $emailSubject = $_POST['subject'];
    // Additional logic can be added here for email processing
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
        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" value="<?= $subject; ?>" size="75"><br><br>

        <label for="bodyfile">Select Email Body File:</label><br>
        <select name="bodyfile" id="bodyfile" required onchange="updateSubject()">
            <option value="">-- Select Body File --</option>
            <?php foreach ($bodyFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit">Save and Continue</button>
    </form>

    <iframe id="bodyPreview" src=""></iframe>

    <script>
        function updateSubject() {
            const bodyFileSelect = document.getElementById('bodyfile');
            const selectedFile = bodyFileSelect.value;
            const iframe = document.getElementById('bodyPreview');

            if (selectedFile) {
                iframe.src = selectedFile; // Update iframe preview
                fetch(`?bodyfile=${encodeURIComponent(selectedFile)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.subject) {
                            document.getElementById('subject').value = data.subject; // Update subject field
                        }
                    })
                    .catch(error => console.error('Error fetching subject:', error));
            } else {
                iframe.src = '';
            }
        }
    </script>
</body>
</html>
