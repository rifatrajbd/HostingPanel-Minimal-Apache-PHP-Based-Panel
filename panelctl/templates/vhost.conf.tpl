<VirtualHost *:80>
    ServerName {{domain}}
    ServerAlias www.{{domain}}
    DocumentRoot {{doc_root}}

    <Directory {{doc_root}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:{{socket}}|fcgi://localhost"
    </FilesMatch>

    # Block access to dotfiles and common sensitive files
    <FilesMatch "^\.|composer\.(json|lock)$">
        Require all denied
    </FilesMatch>

    # Per-site access rules (e.g. Cloudflare-only mode)
    IncludeOptional /etc/hostingpanel/site-access/{{domain}}.conf

    ErrorLog {{home}}/logs/error.log
    CustomLog {{home}}/logs/access.log combined
</VirtualHost>
