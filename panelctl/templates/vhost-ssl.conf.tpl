<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName {{domain}}
    ServerAlias *.{{domain}}
    DocumentRoot {{doc_root}}

    SSLEngine on
    SSLCertificateFile {{cert}}
    SSLCertificateKeyFile {{key}}
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    <Directory {{doc_root}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:{{socket}}|fcgi://localhost"
    </FilesMatch>

    <FilesMatch "^\.|composer\.(json|lock)$">
        Require all denied
    </FilesMatch>

    IncludeOptional /etc/hostingpanel/site-access/{{domain}}.conf
    IncludeOptional /etc/hostingpanel/site-access/{{domain}}.ipmode.conf

    ErrorLog {{home}}/logs/error.log
    CustomLog {{home}}/logs/access.log combined
</VirtualHost>
</IfModule>
