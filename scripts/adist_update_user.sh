#!/bin/bash
while true
do
php8.4 /var/www/html/autoconnect/artisan app:ADist-update-user-status-command
    sleep 1
done
