#!/bin/bash
while true
do

# Call Commands
php8.4 /var/www/html/autoconnect/artisan app:ADist-make-call-command

    sleep 60
done
