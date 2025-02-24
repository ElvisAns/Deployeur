# Deployeur: The Indie Hacker's CI/CD Solution
![image](https://github.com/user-attachments/assets/178d6cdc-e443-4636-afb9-9edf57686fe2)

![CI](https://github.com/ElvisAns/Deployeur/actions/workflows/test.yaml/badge.svg)

## Overview
Deployeur is a lightweight yet powerful deployment tool designed specifically for indie hackers who struggle to set up CI/CD on shared hosting environments. It simplifies the deployment process by automating updates, executing necessary build steps, and maintaining a streamlined workflow—all from a dedicated subdomain.

## How It Works
Deployeur operates from a subdomain that points to the script's root folder, allowing seamless access to deployment functions via the web. With minimal setup, you can trigger deployments securely using an API request, ensuring your application stays up to date with minimal hassle.

## Directory Structure
```
/deployeur/
    ├── script/
    │   ├── deploy.sh        # Main deployment script
    │   ├── deploy.yml       # YAML configuration file for deployment commands
    │   ├── log.txt          # Deployment log file
    ├── tmp/                 # Temporary storage during deployment
├── vendor/                  # Dependencies
.env.example                 # Example environment variables file
index.php                    # Web-triggered deployment script
setup.sh                     # Interactive setup script
```

## Minimum Requirements
Deployeur is lightweight and can run on almost any shared hosting with the following minimum requirements:
- PHP 8.2 or later
- SSH access (optional but recommended)
- `exec()` function enabled in PHP
- Git installed on the server
- Composer & Node.js installed (if using frameworks that require it)

## Setup & Configuration

### 1. Create a Subdomain and Download Deployeur
- Set up a subdomain (e.g., `deploy.yourdomain.com`) in your hosting control panel.
- Download the latest stable release inside the subdomain root:
```sh
cd /home/demo/awesome-deployer
tar -xzf <(curl -sL $(curl -s https://api.github.com/repos/ElvisAns/Deployeur/releases/latest | grep tarball_url | cut -d '"' -f 4)) --strip-components=1
```
### 2. Run the Interactive Setup Script
Deployeur now includes an interactive setup script that configures the environment variables for you. Simply run:
```sh
bash setup.sh
```
This script will prompt you to enter:
- `DEPLOY_TOKEN`
- `PROJECT_FOLDER`
- `REPO_URL`
- `DEPLOYER_FOLDER`
- `TMP_FOLDER`
- `LOG_FILE`
- `DEPLOY_YML`
- `GIT_USERNAME`
- `GIT_EMAIL`

If a `.env` file already exists, the script will warn you and ask for confirmation before backing it up as `.env.backup`.

### 3. Set Permissions
Ensure the script has execution permission:
```sh
chmod +x /home/demo/awesome-deployer/deployer/script/deploy.sh
```
The web server user must also have permission to execute the script and modify files in `PROJECT_FOLDER`.

### Alternative Installation via SSH
If you do not have access to cPanel’s terminal, you can install Deployeur via SSH. Simply connect to your server using:
```sh
ssh your-user@yourdomain.com
```
Then follow the same installation steps as above.

## Deployment Process
1. The script checks for `.deployer.lock` to prevent multiple deployments from running simultaneously.
2. If `deployment.initiated`does not exists, it:
   - Moves everything (except `deployer`, `vendor`, and `node_modules`) to `/deployer/tmp/`.
   - Deletes `vendor` and `node_modules`.
   - Clones the repository into `PROJECT_FOLDER`.
   - Restores necessary files from `/deployer/tmp/`.
   - Commits any restored changes.
3. Executes the commands specified in `deploy.yml` (e.g., `git pull`, `npm install`, `composer install`, `npm run build`).
4. Logs the deployment status.

### GitHub Actions Integration
You can trigger deployment automatically when you push changes to your repository using GitHub Actions:

#### 1. Set Repository Secret
Go to **GitHub > Your Repository > Settings > Secrets and Variables > Actions** and add a new **repository secret**:
- Name: `DEPLOY_TOKEN`
- Value: `your_secret_token`

#### 2. Add GitHub Actions Workflow
Create a `.github/workflows/deploy.yml` file in your repository with the following content:
```yml
name: Deploy

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger Deployeur
        run: |
          curl -H "x-deploy-token: ${{ secrets.DEPLOY_TOKEN }}" http://deploy.yourdomain.com/index.php
```

## Deployment Log
All actions and outputs are logged in `log.txt` for debugging and tracking.

## Security Considerations
- Ensure `DEPLOY_TOKEN` is kept secret to prevent unauthorized deployments.
- The deployment script should only be writable by trusted users.
- The deployment process runs under the web server user, so make sure permissions are correctly set.

## Troubleshooting
- If deployment fails, check `log.txt` for errors.
- Ensure the web server user has permission to execute `deploy.sh` and modify `PROJECT_FOLDER`.
- Verify that `.env` is correctly configured and contains valid values.

## License
This deployment system is licensed under MIT.

## Author
Ansima

