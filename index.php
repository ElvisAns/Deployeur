<?php
/**
 * Deployment Trigger Script
 * 
 * This script triggers the deployment process when accessed via a GET request.
 * It validates the deploy token, executes the deployment script, and logs the output.
 * 
 * @Author: Ansima
 * Created: 02/18/2025
 * Updated: 02/21/2025
 * 
 * Usage:
 * - Make a GET request with the correct `x-deploy-token` header.
 * - The script will execute the deployment process and return the status.
 * 
 * Environment Variables:
 * - DEPLOY_TOKEN: The secret token for authentication.
 * - DEPLOYER_FOLDER: Path to the deployer scripts.
 * - LOG_FILE: Path to the log file.
*/

// Reject non-GET requests in a sneaky way

require __DIR__ . '/vendor/autoload.php'; // Load Composer autoload

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function env($key, $default = null)
{
    $value = $_ENV[$key];
    return $value === false ? $default : $value;
}

// Set the log file path using an environment variable (defaulting to a local log.txt)
$logFile = env('LOG_FILE', __DIR__ . '/deployer/script/log.txt');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sleep(rand(3, 10)); // Random delay to waste their time
    echo 'Successfully deployed :-)';
    // Append message to log.txt
    $msg = date('Y-m-d H:i:s') . " - Bot detected due to wrong request method: {$_SERVER['REMOTE_ADDR']} using {$_SERVER['REQUEST_METHOD']}\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    exit;
}

// Validate the deploy token
$allheaders = getallheaders();
$deployToken = $allheaders['x-deploy-token'] ?? '';
$expectedToken = $_ENV['DEPLOY_TOKEN']; // Get deploy token from .env

if (empty($deployToken)) {
    //sleep(rand(3, 10)); // Random delay to waste their time
    echo 'Successfully deployed :-)';
    $msg = date('Y-m-d H:i:s') . " - Bot detected due to missing deploy token from {$_SERVER['REMOTE_ADDR']}\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    exit;
}

if ($deployToken !== $expectedToken) {
    http_response_code(403);
    die('Unauthorized');
}

// Full path to the deploy script (ensure the web server user has permission to execute it)
$deployScript = "/bin/bash " . env('DEPLOYER_FOLDER') . "/deploy.sh";

// Execute the deploy script and capture output and return code
$output = [];
$returnCode = 0;
exec($deployScript . ' 2>&1', $output, $returnCode);

// Determine deployment status based on the return code
$deployStatus = ($returnCode === 0) ? "Deployment Succeeded" : "Deployment Failed";

// Set content type for plain text output
header('Content-Type: text/plain');

// Enhance output formatting with headers and separators
$report = "==== Deployment Report ====\n";
$report .= "Date: " . date('Y-m-d H:i:s') . "\n";
$report .= "Deploy Script: " . $deployScript . "\n";
$report .= "Return Code: " . $returnCode . "\n";
$report .= "Deployment Status: " . $deployStatus . "\n";
$report .= "----------------------------------------\n";
$report .= implode("\n", $output) . "\n";
$report .= "========================================\n";

// Output the report to the browser
echo $report;

// Also append the report to the log file
file_put_contents($logFile, $report, FILE_APPEND);
