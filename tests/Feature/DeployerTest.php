<?php

/**
 * Get the list of directories and files in the given path.
 *
 * @param  string $path The path to scan for directories and files.
 * @return ["dirs" => $actualDirs, "files" => $actualFiles];
 */
function getFilesAndDirs(string $path): array
{
    $actualDirs = array_map('basename', glob($path . '/*', GLOB_ONLYDIR));
    $actualFiles = array_map('basename', glob($path . '/*.*'));
    return ["dirs" => $actualDirs, "files" => $actualFiles];
}

describe(
    'Shellcheck Validation',
    function () {
        $ROOTDIR = realpath(__DIR__ . '/../../');
        $script_path = $ROOTDIR . "/deployer/script/deploy.sh";

        // Run shellcheck on the deploy script
        $shellcheck_output = [];
        $shellcheck_code = 0;
        exec("shellcheck " . $script_path . " 2>&1", $shellcheck_output, $shellcheck_code);

        test(
            'Shellcheck must be successful',
            function () use ($shellcheck_code) {
                expect($shellcheck_code)->toBe(0);
            }
        );
        test(
            'Shellcheck output must be empty',
            function () use ($shellcheck_output) {
                expect($shellcheck_output)->toBe([]);
            }
        );
    }
);

describe(
    'Deploy script can deploy inside an empty directory',
    function () {
        backupEnv();
        $tmp_app_full_path = __DIR__ . "/tmp-application-folder-" . time(); //tmp folder will need to be deleted manually
        if (!mkdir($tmp_app_full_path) && !is_dir($tmp_app_full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmp_app_full_path));
        }
        test(
            'folder was created',
            function () use ($tmp_app_full_path) {
                expect(is_dir($tmp_app_full_path))->toBe(true);
            }
        );
        $ROOTDIR = realpath(__DIR__ . '/../../');
        $env_content = <<<ENV
        DEPLOY_TOKEN=deploytoken
        PROJECT_FOLDER="$tmp_app_full_path"
        REPO_URL="https://github.com/ElvisAns/deployeur-test.git"
        DEPLOYER_FOLDER="$ROOTDIR/deployer/script"
        TMP_FOLDER="$ROOTDIR/deployer/tmp"
        LOG_FILE="$ROOTDIR/tests/Feature/deploy-log.test.txt"
        DEPLOY_YML="$ROOTDIR/tests/Feature/deploy.test.yml"
        GIT_USERNAME="johndoe"
        GIT_EMAIL="johndoe@gmail.com"
        ENV;
        file_put_contents(ENVFILE, str_replace("\\", "/", $env_content));

        $script_path = $ROOTDIR . "/deployer/script/deploy.sh";
        $deployScript = "bash " . $script_path;
        // Execute the deploy script and capture output and return code
        $output = [];
        $returnCode = 0;
        exec("chmod +x " . $script_path . " && " . $deployScript . ' 2>&1', $output, $returnCode);
        // Determine deployment status based on the return code
        $deployStatus = ($returnCode === 0) ? "Deployment Succeeded" : "Deployment Failed";

        test(
            'deployment must be successful',
            function () use ($deployStatus) {
                expect($deployStatus)->toBe("Deployment Succeeded");
            }
        );

        $expectedDirs = ["node_modules", "public", "src", "dist", ".git"]; //deploy.test.yml runs npm install
        $expectedFiles = [
            ".gitignore",
            "eslint.config.js",
            "index.html",
            "package-lock.json",
            "package.json",
            "README.md",
            "tsconfig.app.json",
            "tsconfig.json",
            "tsconfig.node.json",
            "vite.config.ts",
            ".deployed.initialized"
        ];

        $current_folder_content = getFilesAndDirs($tmp_app_full_path);

        test(
            'temp app folder content must have expected folders after initial deployment',
            function () use ($current_folder_content, $expectedDirs) {
                expect(array_diff($current_folder_content['dirs'], $expectedDirs))->toBeEmpty();
                expect(array_diff($expectedDirs, $current_folder_content['dirs']))->toBeEmpty();
            }
        );

        test(
            'temp app folder content must have expected files after initial deployment',
            function () use ($current_folder_content, $expectedFiles) {
                expect(array_diff($current_folder_content["files"], $expectedFiles))->toBeEmpty();
                expect(array_diff($expectedFiles, $current_folder_content["files"]))->toBeEmpty();
            }
        );

        restoreEnv();
        unlink("$ROOTDIR/tests/Feature/deploy-log.test.txt");
    }
);


describe(
    'Deploy script can deploy inside a non empty directory and merge tracked file changes',
    function () {
        backupEnv();
        $tmp_app_full_path = __DIR__ . "/tmp-application-folder-t-" . time(); //tmp folder will need to be deleted manually
        if (!mkdir($tmp_app_full_path) && !is_dir($tmp_app_full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmp_app_full_path));
        }
        test(
            'folder was created',
            function () use ($tmp_app_full_path) {
                expect(is_dir($tmp_app_full_path))->toBe(true);
            }
        );
        $ROOTDIR = realpath(__DIR__ . '/../../');
        $env_content = <<<ENV
        DEPLOY_TOKEN=deploytoken
        PROJECT_FOLDER="$tmp_app_full_path"
        REPO_URL="https://github.com/ElvisAns/deployeur-test.git"
        DEPLOYER_FOLDER="$ROOTDIR/deployer/script"
        TMP_FOLDER="$ROOTDIR/deployer/tmp"
        LOG_FILE="$ROOTDIR/tests/Feature/deploy-log.test.txt"
        DEPLOY_YML="$ROOTDIR/tests/Feature/deploy.test.yml"
        GIT_USERNAME="johndoe"
        GIT_EMAIL="johndoe@gmail.com"
        ENV;
        file_put_contents(ENVFILE, str_replace("\\", "/", $env_content));

        $htmlContent = <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <link rel="icon" type="image/svg+xml" href="/vite.svg" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>Deployeur - Test</title>
        </head>
        <body>
            <div id="root"></div>
            <script type="module" src="/src/main.tsx"></script>
        </body>
        </html>
        HTML;
        $htmlPath = $tmp_app_full_path . "/index.html";
        file_put_contents($htmlPath, $htmlContent);

        $script_path = $ROOTDIR . "/deployer/script/deploy.sh";
        $deployScript = "bash " . $script_path;
        // Execute the deploy script and capture output and return code
        $output = [];
        $returnCode = 0;
        exec("chmod +x " . $script_path . " && " . $deployScript . ' 2>&1', $output, $returnCode);
        // Determine deployment status based on the return code
        $deployStatus = ($returnCode === 0) ? "Deployment Succeeded" : "Deployment Failed";

        test(
            'deployment must be successful',
            function () use ($deployStatus) {
                expect($deployStatus)->toBe("Deployment Succeeded");
            }
        );

        $actualHtmlContent = file_get_contents($htmlPath);
        test(
            'index.html must have been deployed with the new content not the one from original server',
            function () use ($actualHtmlContent, $htmlContent) {
                expect($actualHtmlContent)->toBe($htmlContent);
            }
        );

        // test if no pending change with git status
        $gitStatus = [];
        $gitStatusCode = 0;
        $current_working_dir = getcwd();
        exec("cd " . $tmp_app_full_path . " && git status 2>&1", $gitStatus, $gitStatusCode);
        test(
            'git status must be successful',
            function () use ($gitStatusCode) {
                expect($gitStatusCode)->toBe(0);
            }
        );
        test(
            'git status must not contain changes related to the index.html file',
            function () use ($gitStatus) { //the index.html should have been commited already
                expect(strpos(join(",", $gitStatus), "index.html"))->toBe(false);
            }
        );
        exec("cd " . $current_working_dir);
        restoreEnv();
        unlink("$ROOTDIR/tests/Feature/deploy-log.test.txt");
    }
);


describe(
    'Deploy script can deploy inside a non empty directory and migrate while keeping untracked files',
    function () {
        backupEnv();
        $tmp_app_full_path = __DIR__ . "/tmp-application-folder-t-" . time(); //tmp folder will need to be deleted manually
        if (!mkdir($tmp_app_full_path) && !is_dir($tmp_app_full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmp_app_full_path));
        }
        test(
            'folder was created',
            function () use ($tmp_app_full_path) {
                expect(is_dir($tmp_app_full_path))->toBe(true);
            }
        );
        $ROOTDIR = realpath(__DIR__ . '/../../');
        $env_content = <<<ENV
        DEPLOY_TOKEN=deploytoken
        PROJECT_FOLDER="$tmp_app_full_path"
        REPO_URL="https://github.com/ElvisAns/deployeur-test.git"
        DEPLOYER_FOLDER="$ROOTDIR/deployer/script"
        TMP_FOLDER="$ROOTDIR/deployer/tmp"
        LOG_FILE="$ROOTDIR/tests/Feature/deploy-log.test.txt"
        DEPLOY_YML="$ROOTDIR/tests/Feature/deploy.test.yml"
        GIT_USERNAME="johndoe"
        GIT_EMAIL="johndoe@gmail.com"
        ENV;
        file_put_contents(ENVFILE, str_replace("\\", "/", $env_content));

        $logContent = <<<LOG
        # This is file is inside the project .gitignore
        # It should not be deleted during deployment
        # In real life scenario, ignored files can be user uploaded stuffs and we should keep them
        LOG;
        $logPath = $tmp_app_full_path . "/test.log";
        file_put_contents($logPath, $logContent);

        $script_path = $ROOTDIR . "/deployer/script/deploy.sh";
        $deployScript = "bash " . $script_path;
        // Execute the deploy script and capture output and return code
        $output = [];
        $returnCode = 0;
        exec("chmod +x " . $script_path . " && " . $deployScript . ' 2>&1', $output, $returnCode);
        // Determine deployment status based on the return code
        $deployStatus = ($returnCode === 0) ? "Deployment Succeeded" : "Deployment Failed";

        test(
            'deployment must be successful',
            function () use ($deployStatus) {
                expect($deployStatus)->toBe("Deployment Succeeded");
            }
        );

        test(
            'test.log must still exist after deployment',
            function () use ($logPath) {
                expect(file_exists($logPath))->toBe(true);
            }
        );

        $actualLogContent = file_get_contents($logPath);
        test(
            'test.log must have been deployed with it\'s original content',
            function () use ($actualLogContent, $logContent) {
                expect($actualLogContent)->toBe($logContent);
            }
        );

        // test if no pending change with git status
        $gitStatus = [];
        $gitStatusCode = 0;
        $current_working_dir = getcwd();
        exec("cd " . $tmp_app_full_path . " && git status 2>&1", $gitStatus, $gitStatusCode);
        test(
            'git status must be successful',
            function () use ($gitStatusCode) {
                expect($gitStatusCode)->toBe(0);
            }
        );
        test(
            'git status must not contain changes related to the test.log file',
            function () use ($gitStatus) { //the modified file is untracked
                expect(strpos(join(",", $gitStatus), "test.log"))->toBe(false);
            }
        );
        exec("cd " . $current_working_dir);
        restoreEnv();
        unlink("$ROOTDIR/tests/Feature/deploy-log.test.txt");
    }
);
