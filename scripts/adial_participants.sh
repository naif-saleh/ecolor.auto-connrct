#!/bin/bash
while true
do
php8.4 /var/www/html/autoconnect/artisan app:ADial-participants-command
    sleep 1
done
