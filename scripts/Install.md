1. Copy the service files to /lib/systemd/system/
```
sudo cp /var/www/html/autoconnect/scripts/services/*.service /lib/systemd/system/
```

2. Reload systemd to detect the new services
```
sudo systemctl daemon-reload
```

3. Enable all services to start on boot

```
sudo systemctl enable adial_make_call.service
sudo systemctl enable adial_participants.service
sudo systemctl enable adist_make_call.service
sudo systemctl enable adist_participants.service
sudo systemctl enable adist_update_user.service
```

4. Start all services

```
sudo systemctl start adial_make_call.service
sudo systemctl start adial_participants.service
sudo systemctl start adist_make_call.service
sudo systemctl start adist_participants.service
sudo systemctl start adist_update_user.service
```

5. Verify Services are Running
```
systemctl status adial_make_call.service
systemctl status adial_participants.service
systemctl status adist_make_call.service
systemctl status adist_participants.service
systemctl status adist_update_user.service

```

If any service is failing, check logs:


```
journalctl -u adial_make_call.service --no-pager --lines=50
```

(Replace with the specific service name if needed.)


make sure that you are changing the values on php-fpm 
`nano /etc/php/8.4/fpm/pool.d/www.conf`
and then restart the services 
`systemctl status php8.4-fpm.service`

If faild to run make sure you give permession to files
```
chmod +x /var/www/html/autoconnect/scripts/replace-with-the-file-name.sh
```
