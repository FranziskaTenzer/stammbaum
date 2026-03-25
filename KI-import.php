<?php
// KI-import.php

echo "File upload form";

echo "<form action='upload.php' method='post' enctype='multipart/form-data'>";

echo "Select file to upload:";

echo "<input type='file' name='fileToUpload' id='fileToUpload'>";

echo "<input type='submit' value='Upload File' name='submit'>";

echo "</form>";

// Logic for handling the uploaded file would go here
?>