-- Unofficial Fix VOD by Diego-GTV

import M3U stream by: christo


## Turn on file uploads in php.ini ##
change php.ini import m3u

sed -i 's,^file_uploads =.*$,file_uploads = On,' /home/xtreamcodes/iptv_xtream_codes/php/lib/php.ini
