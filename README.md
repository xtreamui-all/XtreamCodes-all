
apt-get install unzip e2fsprogs python-paramiko -y && chattr -i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb && rm -rf /home/xtreamcodes/iptv_xtream_codes/admin && rm -rf /home/xtreamcodes/iptv_xtream_codes/pytools && rm -rf /home/xtreamcodes/iptv_xtream_codes/adtools && wget "https://github.com/xtreamui-all/XtreamCodes-all/archive/master.zip" -O /tmp/update.zip -o /dev/null && unzip /tmp/update.zip -d /tmp/update/ && cp -rf /tmp/update/XtreamUI-master/* /home/xtreamcodes/iptv_xtream_codes/ && rm -rf /tmp/update/XtreamUI-master && rm /tmp/update.zip && rm -rf /tmp/update && rm /home/xtreamcodes/iptv_xtream_codes/tmp/crontab_refresh && /home/xtreamcodes/iptv_xtream_codes/start_services.sh && chattr +i /home/xtreamcodes/iptv_xtream_codes/GeoLite2.mmdb



-- Unofficial Fix VOD by Diego-GTV

import M3U stream by: christo


## Turn on file uploads in php.ini ##
change php.ini import m3u

sed -i 's,^file_uploads =.*$,file_uploads = On,' /home/xtreamcodes/iptv_xtream_codes/php/lib/php.ini
