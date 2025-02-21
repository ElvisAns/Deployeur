#!/bin/bash
#
# Deployment Script
#
# This script automates the deployment process by:
# - Checking for a lock file to prevent multiple deployments
# - Moving existing files (except specific folders) to a temporary location
# - Cloning the repository from the configured .env file
# - Restoring previous files and committing changes if any
# - Executing deployment commands from a YAML configuration file
#
# Author: Ansima
# Created: 02/17/2025
# Updated: 02/21/2025
#
# Environment Variables:
# - PROJECT_FOLDER: Full path to the project directory
# - DEPLOYER_FOLDER: Full path to the deployer scripts
# - REPO_URL: Git repository URL to clone
# - LOG_FILE: Path to the log file
# - GIT_USERNAME : The username to use as commit author name
# - GIT_EMAIL : The email to use as commit author email
#
# Usage:
# - Ensure this script is executable: chmod +x deploy.sh
# - Execute manually or trigger via the web deployment script
#
# Notes:
# - The script uses a lock file (.deployer.lock) to prevent multiple deployments at the same time.
# - A deployment flag file (deployment.initiated) determines when initial steps should run.
# - Deployment commands are read from a YAML file to allow flexibility.
#

# Exit immediately if any command fails
set -e

# Load environment variables from .env file
ENV_FILE="$(dirname "$0")/../../.env"
if [ -f "$ENV_FILE" ]; then
  source "$ENV_FILE"
else
  echo "Error: .env file not found at $ENV_FILE"
  exit 1
fi

# Change directory to the project folder
cd "$PROJECT_FOLDER" || { echo "Error: Cannot change directory to $PROJECT_FOLDER"; exit 1; }

# Define file paths for lock and initial flag (both stored in PROJECT_FOLDER)
LOCK_FILE="$PROJECT_FOLDER/.deployer.lock"
INITIATED_FLAG="$PROJECT_FOLDER/deployment.initiated"

# Check if a deployment is already in progress
if [ -f "$LOCK_FILE" ]; then
  echo "Deployment already in progress. Exiting."
  exit 1
fi

# Create the lock file to prevent concurrent deployments
touch "$LOCK_FILE"

# Run initial merge steps only if deployment hasn't been initiated yet
if [ ! -f "$INITIATED_FLAG" ]; then
  echo "Initial deployment steps: deployment.initiated not found."

  # Create temporary folder if it doesn't exist
  mkdir -p "$TMP_FOLDER"

  # Enable extended globbing and include dotfiles
  shopt -s extglob dotglob

  # Move all items except 'deployer', 'vendor', and 'node_modules' to TMP_FOLDER
  shopt -s extglob
  for item in *; do
    if [[ "$item" != "deployer" && "$item" != "vendor" && "$item" != "node_modules" ]]; then
      mv -v "$item" "$TMP_FOLDER"/ 2>/dev/null || true
    fi
  done
  shopt -u extglob

  # Delete node_modules and vendor directories to ensure a clean state
  echo "Removing node_modules and vendor directories..."
  rm -rf "$PROJECT_FOLDER/node_modules" "$PROJECT_FOLDER/vendor"

  # Clone the repository from the URL into the PROJECT_FOLDER
  git clone "$REPO_URL" . || { echo "Error: git clone failed"; rm "$LOCK_FILE"; exit 1; }

  # Move back the previously moved files from TMP_FOLDER to PROJECT_FOLDER
  mv -v "$TMP_FOLDER"/* "$PROJECT_FOLDER"/ 2>/dev/null || true

  # Check if there are any changes after merging
  if [ -n "$(git status --porcelain)" ]; then
    # Check if git config is set, if not set it using env variables
    if [ -z "$(git config user.name)" ]; then
      git config user.name "$GIT_USERNAME"
    fi
    if [ -z "$(git config user.email)" ]; then
      git config user.email "$GIT_EMAIL" 
    fi
    git add .
    git commit -m "Initial merge: auto commit changes from previous deployment" || echo "Nothing to commit"
    echo "$(date): Changes detected and auto-committed during initial merge." >> "$LOG_FILE"
  fi

  # Create the deployment initiated flag so that initial steps are not run again
  touch "$INITIATED_FLAG"
else
  echo "Initial deployment already completed (deployment.initiated found)."
fi

# Execute deploy commands from the YAML file
if [ -f "$DEPLOY_YML" ]; then
  # Extract commands (lines starting with "- ") under the deploy section
  deploy_commands=$(grep '^- ' "$DEPLOY_YML" | sed 's/^- //')
  for cmd in $deploy_commands; do
    echo "Executing command: $cmd"
    eval "$cmd"
    if [ $? -ne 0 ]; then
      echo "Error: Command failed -> $cmd"
      rm "$LOCK_FILE"
      exit 1
    fi
  done
else
  echo "Error: Deployment YAML file not found at $DEPLOY_YML"
  rm "$LOCK_FILE"
  exit 1
fi

# Remove the lock file now that deployment is complete
rm "$LOCK_FILE"

echo "Deployment completed successfully."
