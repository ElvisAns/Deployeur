#!/bin/bash

# Define colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

cat << "EOF"
     ___     __  __      ___   _____     __  ___ __      __     ___     __  
|  ||__ |   /  `/  \|\/||__     |/  \   |  \|__ |__)|   /  \\ /|__ |  ||__) 
|/\||___|___\__,\__/|  ||___    |\__/   |__/|___|   |___\__/ | |___\__/|  \ 

Please setup the initial configuration
                                                                            
EOF

# Check if .env already exists
if [ -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env file already exists!${NC}"
    read -p "Do you want to overwrite it? (y/n): " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        mv .env .env.backup
        echo -e "${GREEN}Existing .env file has been backed up as .env.backup${NC}"
    else
        echo -e "${RED}Setup aborted. Your .env file remains unchanged.${NC}"
        exit 1
    fi
fi

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Composer is not installed. Please install Composer and run the setup again.${NC}"
    exit 1
fi

# Run composer install
echo -e "${BLUE}Running composer install...${NC}"
composer install

if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed. Please check the errors above.${NC}"
    exit 1
fi
# Ask for user input with default values
echo -e "${BLUE}Please enter the following configuration values:${NC}"

read -p "Enter Deploy Token [xxxxxxxxx]: " DEPLOY_TOKEN
DEPLOY_TOKEN=${DEPLOY_TOKEN:-xxxxxxxxx}

read -p "Enter Project Folder [/home/demo/public_html]: " PROJECT_FOLDER
PROJECT_FOLDER=${PROJECT_FOLDER:-"/home/demo/public_html"}

read -p "Enter Repo URL [https://github_pat_xxxxxxxxxxx@github.com//username/repo.git]: " REPO_URL
REPO_URL=${REPO_URL:-"https://github_pat_xxxxxxxxxxx@github.com//username/repo.git"}

read -p "Enter Deployer Folder [/home/demo/awesome-deployer/deployer/script]: " DEPLOYER_FOLDER
DEPLOYER_FOLDER=${DEPLOYER_FOLDER:-"/home/demo/awesome-deployer/deployer/script"}

read -p "Enter Temp Folder [/home/demo/awesome-deployer/deployer/tmp]: " TMP_FOLDER
TMP_FOLDER=${TMP_FOLDER:-"/home/demo/awesome-deployer/deployer/tmp"}

read -p "Enter Log File Path [/home/demo/awesome-deployer/deployer/script/log.txt]: " LOG_FILE
LOG_FILE=${LOG_FILE:-"/home/demo/awesome-deployer/deployer/script/log.txt"}

read -p "Enter Deploy YML Path [/home/demo/awesome-deployer/deployer/script/deploy.yml]: " DEPLOY_YML
DEPLOY_YML=${DEPLOY_YML:-"/home/demo/awesome-deployer/deployer/script/deploy.yml"}

read -p "Enter Git Username [johndoe]: " GIT_USERNAME
GIT_USERNAME=${GIT_USERNAME:-johndoe}

read -p "Enter Git Email [johndoe@gmail.com]: " GIT_EMAIL
GIT_EMAIL=${GIT_EMAIL:-"johndoe@gmail.com"}

# Write to .env file
cat <<EOL > .env
DEPLOY_TOKEN=$DEPLOY_TOKEN
PROJECT_FOLDER="$PROJECT_FOLDER"
REPO_URL="$REPO_URL"
DEPLOYER_FOLDER="$DEPLOYER_FOLDER"
TMP_FOLDER="$TMP_FOLDER"
LOG_FILE="$LOG_FILE"
DEPLOY_YML="$DEPLOY_YML"
GIT_USERNAME="$GIT_USERNAME"
GIT_EMAIL="$GIT_EMAIL"
EOL

# Set executable permissions for deploy script
if ! chmod +x "$DEPLOYER_FOLDER/deploy.sh" 2>/dev/null; then
    echo -e "${RED}Could not set executable permissions. Please run the following command manually:${NC}"
    echo "chmod +x \"$DEPLOYER_FOLDER/deploy.sh\""
fi
echo -e "${GREEN}Setup completed! You can now use Deployeur.${NC}"
