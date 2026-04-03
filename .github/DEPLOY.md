# Guide de déploiement BengaDok

## Architecture CI/CD

```
Push sur main ou PR vers main
     │
     ▼
[ci.yml] Lint (Pint) + Tests (PHP 8.3)
     │
     ▼ (push sur main uniquement, si CI passe)
Déploiement VPS via SSH
     │
     ├── php artisan down      (maintenance)
     ├── git pull
     ├── composer install
     ├── npm ci + npm run build
     ├── php artisan migrate
     ├── config/route/view cache
     ├── queue:restart
     └── php artisan up        (fin maintenance)
```

---

## 1. Secrets GitHub à configurer

Aller dans : **GitHub repo → Settings → Secrets and variables → Actions**

| Secret | Exemple | Description |
|--------|---------|-------------|
| `VPS_HOST` | `192.168.1.10` ou `bengadok.com` | IP ou domaine du VPS |
| `VPS_USERNAME` | `deployer` | Utilisateur SSH |
| `VPS_SSH_KEY` | `-----BEGIN OPENSSH...` | Clé privée SSH complète |
| `VPS_PORT` | `22` | Port SSH (optionnel, 22 par défaut) |
| `DEPLOY_PATH` | `/var/www/bengadok` | Chemin absolu de l'app sur le VPS |

> **Note** : `VPS_SSH_KEY` = contenu complet de `~/.ssh/id_rsa` (ou ed25519).
> La clé publique correspondante doit être dans `~/.ssh/authorized_keys` sur le VPS.

---

## 2. Préparation du VPS (première fois seulement)

### Prérequis
```bash
# PHP 8.3+
sudo apt install php8.3 php8.3-{cli,fpm,mysql,mbstring,xml,bcmath,gd,curl,zip,intl}

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install nodejs

# MySQL
sudo apt install mysql-server

# Nginx
sudo apt install nginx
```

### Créer l'utilisateur deployer
```bash
sudo adduser deployer
sudo usermod -aG www-data deployer

# Autoriser deployer à redémarrer php-fpm et nginx sans mot de passe
sudo visudo
# Ajouter : deployer ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.3-fpm, /bin/systemctl reload nginx
```

### Cloner le dépôt sur le VPS
```bash
sudo mkdir -p /var/www/bengadok
sudo chown deployer:www-data /var/www/bengadok
sudo chmod 775 /var/www/bengadok

# En tant que deployer
su - deployer
git clone git@github.com:TON_USER/bengadok.git /var/www/bengadok
cd /var/www/bengadok

# Configurer le .env (UNE SEULE FOIS — ne jamais commiter)
cp .env.example .env
nano .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed  # premier déploiement seulement

# Permissions storage
chmod -R 775 storage bootstrap/cache
chown -R deployer:www-data storage bootstrap/cache
```

### Configuration MySQL
```sql
CREATE DATABASE bengadok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bengadok'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON bengadok.* TO 'bengadok'@'localhost';
FLUSH PRIVILEGES;
```

### .env production (variables importantes)
```env
APP_NAME=BengaDok
APP_ENV=production
APP_KEY=           # généré par artisan key:generate
APP_DEBUG=false
APP_URL=https://bengadok.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bengadok
DB_USERNAME=bengadok
DB_PASSWORD=MOT_DE_PASSE_FORT

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=daily
LOG_LEVEL=error
```

---

## 3. Configuration Nginx

```nginx
# /etc/nginx/sites-available/bengadok
server {
    listen 80;
    server_name bengadok.com www.bengadok.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name bengadok.com www.bengadok.com;

    root /var/www/bengadok/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/bengadok.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bengadok.com/privkey.pem;

    # Taille max upload (ordonnances)
    client_max_body_size 15M;

    # Logs
    access_log /var/log/nginx/bengadok.access.log;
    error_log  /var/log/nginx/bengadok.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache assets Vite
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/bengadok /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# SSL avec Certbot
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d bengadok.com -d www.bengadok.com
```

---

## 4. Worker de queue (Supervisor)

```bash
sudo apt install supervisor
```

```ini
# /etc/supervisor/conf.d/bengadok-worker.conf
[program:bengadok-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bengadok/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deployer
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bengadok-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bengadok-worker:*
```

---

## 5. Générer la clé SSH pour le déploiement

```bash
# Sur ta machine locale (ou sur le VPS en tant que deployer)
ssh-keygen -t ed25519 -C "github-deploy-bengadok" -f ~/.ssh/bengadok_deploy

# Copier la clé publique sur le VPS
ssh-copy-id -i ~/.ssh/bengadok_deploy.pub deployer@TON_VPS

# Contenu de la clé PRIVÉE à mettre dans le secret VPS_SSH_KEY
cat ~/.ssh/bengadok_deploy
```

---

## 6. Environnement GitHub (optionnel mais recommandé)

Créer un environnement `production` dans **GitHub → Settings → Environments** :
- Ajouter une règle de protection : seule la branche `main` peut déployer
- Optionnel : ajouter des reviewers requis avant déploiement

---

## Flux de travail quotidien

```bash
# Développement local
git checkout -b feature/ma-feature
# ... code ...
git push origin feature/ma-feature
# → Ouvrir PR vers main
# → CI s'exécute automatiquement sur la PR

# Merge PR sur main
# → CI + Deploy s'exécutent automatiquement
```
