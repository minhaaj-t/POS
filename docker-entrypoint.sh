#!/bin/bash
set -e

# Use PORT environment variable if set, otherwise default to 8080
PORT=${PORT:-8080}

# Update Apache configuration to use the PORT
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf

# Start Apache
exec apache2-foreground

