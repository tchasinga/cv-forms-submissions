<?php
// Simple test script to verify file upload functionality
echo "<h2>File Upload Test</h2>";

// Check if uploads directory exists
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "<p>✅ Uploads directory exists</p>";
} else {
    echo "<p>❌ Uploads directory does not exist</p>";
}

// Check directory permissions
if (is_writable($upload_dir)) {
    echo "<p>✅ Uploads directory is writable</p>";
} else {
    echo "<p>❌ Uploads directory is not writable</p>";
}

// Check directory permissions in detail
$perms = fileperms($upload_dir);
echo "<p>Directory permissions: " . substr(sprintf('%o', $perms), -4) . "</p>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";

// Check temporary directory
$tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
echo "<p>Upload temp directory: " . $tmp_dir . "</p>";
echo "<p>Temp directory writable: " . (is_writable($tmp_dir) ? 'Yes' : 'No') . "</p>";

// Test creating a file
$test_file = $upload_dir . 'test.txt';
if (file_put_contents($test_file, 'Test content')) {
    echo "<p>✅ Can create files in uploads directory</p>";
    unlink($test_file); // Clean up
} else {
    echo "<p>❌ Cannot create files in uploads directory</p>";
}

// Test file upload simulation
echo "<h3>File Upload Simulation:</h3>";
$test_upload_file = $upload_dir . 'test_upload.txt';
$test_content = 'Test upload content';

// Simulate the upload process
if (file_put_contents($test_upload_file, $test_content)) {
    echo "<p>✅ File creation test passed</p>";
    
    // Test if we can read the file back
    if (file_get_contents($test_upload_file) === $test_content) {
        echo "<p>✅ File read test passed</p>";
    } else {
        echo "<p>❌ File read test failed</p>";
    }
    
    // Clean up
    unlink($test_upload_file);
} else {
    echo "<p>❌ File creation test failed</p>";
}

// Check web server user
echo "<h3>Web Server Information:</h3>";
echo "<p>Current user: " . get_current_user() . "</p>";
echo "<p>Process user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Unknown') . "</p>";

echo "<p><a href='index.php'>Back to main form</a></p>";
?>
