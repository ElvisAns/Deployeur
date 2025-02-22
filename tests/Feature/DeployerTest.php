<?php

/**
 * Get the list of directories and files in the given path.
 *
 * @param string $path The path to scan for directories and files.
    return ["dirs" => $actualDirs, "files" => $actualFiles];
 */
function getFilesAndDirs(string $path): array
{
    $actualDirs = array_map('basename', glob($path . '/*', GLOB_ONLYDIR));
    $actualFiles = array_map('basename', glob($path . '/*.*'));
    return ["dirs"=>$actualDirs,"files"=>$actualFiles];
}

describe(
    'Deploy script can deploy inside an empty directory and verify the presence of expected directories and files', function () {
        backupEnv();
        $tmp_app_full_path = __DIR__."/tmp-application-folder";
        if (!mkdir($tmp_app_full_path) && !is_dir($tmp_app_full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmp_app_full_path));
        }
        test(
            'folder was created', function () use ($tmp_app_full_path) {
                expect(is_dir($tmp_app_full_path))->toBe(true);
            }
        );
        $ROOTDIR = realpath(__DIR__.'/../../');
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

        $script_path = $ROOTDIR. "/deployer/script/deploy.sh";
        $deployScript = "bash ".$script_path;

        // Run shellcheck to validate the script
        $shellcheck_output = [];
        $shellcheck_code = 0;
        exec("shellcheck " . $script_path . " 2>&1", $shellcheck_output, $shellcheck_code);
        
        // Execute the deploy script and capture output and return code
        $output = [];
        $returnCode = 0;
        exec("chmod +x ".$script_path." && ".$deployScript . ' 2>&1', $output, $returnCode);

        test(
            'shellcheck must be successful', function () use ($shellcheck_code) {
                expect($shellcheck_code)->toBe(0);
            }
        );

        test(
            'shellcheck output must be empty', function () use ($shellcheck_output) {
                expect($shellcheck_output)->toBe([]);
            }
        );

        // Determine deployment status based on the return code
        $deployStatus = ($returnCode === 0) ? "Deployment Succeeded" : "Deployment Failed";

        test(
            'deployment must be successful', function () use ($deployStatus) {
                expect($deployStatus)->toBe("Deployment Succeeded");
            }
        );

        $expectedDirs = ["node_modules", "public", "src"]; //deploy.test.yml runs npm install
        $expectedFiles = [
            ".gitignore", "eslint.config.js", "index.html", "package-lock.json",
            "package.json", "README.md", "tsconfig.app.json", "tsconfig.json",
            "tsconfig.node.json", "vite.config.ts"
        ];

        $current_folder_content = getFilesAndDirs($tmp_app_full_path);

        test(
            'temp app folder content must have expected folders after initial deployment', function () use ($current_folder_content, $expectedDirs) {
                expect($current_folder_content["dirs"])->toBe($expectedDirs);
            }
        );

        test(
            'temp app folder content must have expected files after initial deployment', function () use ($current_folder_content, $expectedFiles) {
                expect($current_folder_content["files"])->toBe($expectedFiles);
            }
        );

        restoreEnv();
        unlink("$ROOTDIR/tests/Feature/deploy-log.test.txt");
        rmdir($tmp_app_full_path);
        if (is_dir($tmp_app_full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not removed', $tmp_app_full_path));
        }
    }
);