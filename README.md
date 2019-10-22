# XtreamCodes-all

- How to INSTALL (Ubuntu 18.04 recommended for MAIN)

As root: apt-get update ; apt-get install libxslt1-dev libcurl3 libgeoip-dev python -y ; wget https://xtream-ui.com/install/install.py ; sudo python install.py
If you want a whole NEW installation, choose MAIN and then ADMIN.
If you want ONLY the admin part, select ADMIN only.

- DUMP Database (Backup)

On your OLD Server
Run as root: mysqldump xtream_iptvpro > xtcdump.sql

- RESTORE Database

Upload xtcdump.sql to your new server (It's OK to use /root)
Run as root: mysql xtream_iptvpro < /root/xtcdump.sql

NOTE: This is ONLY for XC V2 to V2! 

- User not working to login on panel after RESTORE DB?

Using SSH: mysql
Now type: UPDATE xtream_iptvpro.reg_users SET password='$6$rounds=20000$xtreamcodes$XThC5OwfuS0YwS4ahiifzF14vkGbGsFF1w7ETL4sRRC5sOrAWCjWvQJDromZUQoQuwbAXAFdX3h3Cp3vqulpS0' WHERE id='1';
UPDATE xtream_iptvpro.reg_users SET username = 'admin' WHERE id = '1';
UPDATE xtream_iptvpro.reg_users SET member_group_id = '1' WHERE id = '1';

Login using admin/admin

- I rebooted the server and the panel didn't come back up

As root run:  /home/xtreamcodes/iptv_xtream_codes/start_services.sh

- How to get m3u playlist?

http://ip.address:25461/get.php?username=user&password=pass&type=m3u&output=ts

- Download m3u not working

Check if the user does NOT have MAG and stuff ENABLED on user config. They must be DISABLED!

- libexslt.so.0 - geoip.so - libcurl ERRORS

Run as root: apt-get install libxslt1-dev libcurl3 libgeoip-dev

- How to ADD Stream on the final step

Move the "Main Server" tile INSIDE the "Stream Source" like this: https://i.imgur.com/E6eqg3p.png

- How to find out user_iptvpro mysql password?

As root run: wget https://raw.githubusercontent.com/xtreamui/XtreamUI/master/pytools/config.py && python config.py DECRYPT && rm config.py

- How to change BROADCAST port from 25461 to another port?

Change NEWPORT to the port you want. Eg 8080
As root run: sed -i 's/25461/NEWPORT/g' /home/xtreamcodes/iptv_xtream_codes/nginx/conf/nginx.conf ; /etc/init.d/xtreamcodes

- How to change ADMIN port from 25500 to another port?

Change NEWPORT to the port you want. Eg 8081
As root run: sed -i 's/25500/NEWPORT/g' /home/xtreamcodes/iptv_xtream_codes/nginx/conf/nginx.conf ; /etc/init.d/xtreamcodes

- How to FORCE RELOAD EPG (If EPG empty on Apps and stuff)

/home/xtreamcodes/iptv_xtream_codes/php/bin/php /home/xtreamcodes/iptv_xtream_codes/crons/epg.php

- How to update from GITHUB release?

As root run: apt-get install unzip e2fsprogs python-paramiko -y && chattr -i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb && rm -rf /home/xtreamcodes/iptv_xtream_codes/admin && rm -rf /home/xtreamcodes/iptv_xtream_codes/pytools && rm -rf /home/xtreamcodes/iptv_xtream_codes/adtools && wget https://github.com/xtreamui/XtreamUI/archive/master.zip -O /tmp/update.zip -o /dev/null && unzip /tmp/update.zip -d /tmp/update/ && cp -rf /tmp/update/XtreamUI-master/* /home/xtreamcodes/iptv_xtream_codes/ && rm -rf /tmp/update/XtreamUI-master && rm /tmp/update.zip && rm -rf /tmp/update && rm /home/xtreamcodes/iptv_xtream_codes/README.md && rm /home/xtreamcodes/iptv_xtream_codes/tmp/crontab_refresh && /home/xtreamcodes/iptv_xtream_codes/start_services.sh && chattr +i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb

Don't forget to clear your browser cache
 

- How to use YouTube in the panel?

As root run:
sudo su ; wget https://s3.us-east-2.amazonaws.com/firez.uploadanime.xyz/ytphp.sh -O "ytphp.sh" ; chmod u+x ytphp.sh ; ./ytphp.sh
 

- Errors AUTO Installing Load Balancers?

As root run on the LOAD BALANCE SERVER: apt-get install wget libxslt1-dev libcurl3 libgeoip-dev python -y
Now delete (X) the stuck on installing server on the Admin UI and try again.

- Fix 500 Errors on XC v2

As root run: apt-get install e2fsprogs -y && chattr -i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb ; wget https://archive.org/download/geolite2_201910/GeoLite2.mmdb -O /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb && chown xtreamcodes.xtreamcodes  /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb  && chattr +i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb && clear && echo "If you see this message, 500 errors are probably fixed"

- How to find out what Network Interface to use on your Admin UI

As root run: route -n | awk '$1 == "0.0.0.0" {print $8}'
After that, on your Admin UI, go to: Servers > Manage Servers > Edit > Advanced
