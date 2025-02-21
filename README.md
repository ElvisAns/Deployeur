# Deployeur: The Indie Hacker's CI/CD Solution

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
```

## Minimum Requirements
Deployeur is lightweight and can run on almost any shared hosting with the following minimum requirements:
- PHP 7.4 or later
- SSH access (optional but recommended)
- `exec()` function enabled in PHP
- Git installed on the server
- Composer & Node.js installed (if using frameworks that require it)

## Setup & Configuration

### 1. Create a Subdomain and Clone the Repository
- Set up a subdomain (e.g., `deploy.yourdomain.com`) in your hosting control panel.
- Clone this repository into the subdomain’s root folder:
```sh
git clone https://github.com/ElvisAns/Deployeur.git /home/demo/awesome-deployer
cd /home/demo/awesome-deployer
```

### 2. Install Dependencies
Ensure you have the required dependencies:
```sh
composer install  # For PHP dependencies
```

### 3. Configure Environment Variables
Copy `.env.example` to `.env` and update the values inside:
```sh
cp .env.example .env
```
Edit `.env` with your configuration:
```
DEPLOY_TOKEN=xxxxxxxxx
PROJECT_FOLDER="/home/demo/public_html"
REPO_URL="https://github_pat_xxxxxxxxxxx@github.com//username/repo.git"
DEPLOYER_FOLDER="/home/demo/awesome-deployer/deployer"
TMP_FOLDER="/home/demo/awesome-deployer/deployer/tmp"
LOG_FILE="/home/demo/awesome-deployer/deployer/script/log.txt"
DEPLOY_YML="/home/demo/awesome-deployer/deployer/script/deploy.yml"
GIT_USERNAME="johndoe"
GIT_EMAIL="johndoe@gmail.com"
```

### 4. Set Permissions
Ensure the script has execution permission:
```sh
chmod +x /home/demo/awesome-deployer/deployer/script/deploy.sh
```
The web server user must also have permission to execute the script and modify files in `PROJECT_FOLDER`.

## Deployment Process
1. The script checks for `.deployer.lock` to prevent multiple deployments from running simultaneously.
2. If `deployment.initiated` exists, it:
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

