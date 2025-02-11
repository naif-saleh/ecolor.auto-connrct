#!/bin/bash
while true
do

# Call Commands
php8.4 /var/www/html/autoconnect/artisan app:ADial-make-call-command

# Call Status Commands
php8.4 /var/www/html/autoconnect/artisan app:ADial-participants-command
php8.4 /var/www/html/autoconnect/artisan app:ADist-participants-command

# Update User Status Commands
php8.4 /var/www/html/autoconnect/artisan app:ADist-update-user-status-command
    sleep 1
done
