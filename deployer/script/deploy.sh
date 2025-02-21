#!/bin/bash
#
# Deployment Script with Rollback Mechanism
#
# This script automates the deployment process and includes a rollback mechanism
# to revert the server to its previous state in case of failure.
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

# Function to rollback the server to its previous state
rollback() {
  echo "Rolling back the server to its previous state..."
  
  # Remove the newly cloned repository
  if [ -d "$PROJECT_FOLDER/.git" ]; then
    rm -rf "$PROJECT_FOLDER/.git"
    rm -rf "$PROJECT_FOLDER"/*
  fi

  # Move back the previously moved files from TMP_FOLDER to PROJECT_FOLDER
  if [ -d "$TMP_FOLDER" ]; then
    mv -v "$TMP_FOLDER"/* "$PROJECT_FOLDER"/ 2>/dev/null || true
  fi

  # Remove the lock file
  rm -f "$LOCK_FILE"

  echo "Rollback completed."
  exit 1
}

# Trap any errors and call the rollback function
trap rollback ERR

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
    if [[ "$item" != "vendor" && "$item" != "node_modules" ]]; then
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

# Function to check if yq is installed, install if not
check_yq() {
  if ! command -v yq &> /dev/null; then
    echo "yq not found, installing locally..."
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
      # For Linux, install yq locally in the current directory
      curl -sSL https://github.com/mikefarah/yq/releases/download/v4.16.1/yq_linux_amd64 -o ./yq
      chmod +x ./yq
    elif [[ "$OSTYPE" == "darwin"* ]]; then
      # For macOS, install yq locally in the current directory
      curl -sSL https://github.com/mikefarah/yq/releases/download/v4.16.1/yq_darwin_amd64 -o ./yq
      chmod +x ./yq
    else
      echo "Unsupported OS. Please install yq manually."
      exit 1
    fi
  fi
}

# Check if yq is installed, or install it locally
check_yq

# Execute deploy commands from the YAML file
if [ -f "$DEPLOY_YML" ]; then
  # Use the locally installed yq to parse the YAML file, with double-quoted commands properly escaped
  deploy_commands=$(./yq e '.deploy[]' "$DEPLOY_YML" | sed 's/\\"/"/g')  # Ensure quotes are properly handled
  for cmd in "$deploy_commands"; do
    echo "Executing command: $cmd"
    eval "$cmd"
    if [ $? -ne 0 ]; then
      echo "Error: Command failed -> $cmd"
      rollback
    fi
  done
else
  echo "Error: Deployment YAML file not found at $DEPLOY_YML"
  rollback
fi

# Remove the lock file now that deployment is complete
rm "$LOCK_FILE"

echo "Deployment completed successfully."