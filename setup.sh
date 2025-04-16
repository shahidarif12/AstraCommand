#!/bin/bash
# Astra C2 - Setup Script

echo "Astra C2 Setup Script"
echo "====================="
echo ""

# Create database directory if it doesn't exist
if [ ! -d "database" ]; then
    echo "Creating database directory..."
    mkdir -p database
fi

# Run PHP database setup script
echo "Setting up SQLite database..."
php database/setup_sqlite.php

echo ""
echo "Setup complete!"
echo "You can now access the C2 panel at http://localhost:5000/c2/panel/"
echo "Default login: admin / admin123"