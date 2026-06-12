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
     ├── queue:restart + supervisorctl restart (workers + Reverb)
     └── php artisan up        (fin maintenance)
```

---

## 1. Secrets GitHub à configurer

Aller dans : **GitHub repo → Settings → Secrets and variables → Actions**

| Secret | Exemple | Description |
|--------|---------|-------------|
| `VPS_HOST` | `192.168.1.10` ou `bengadok.com` | IP ou domaine du VPS |
| `VPS_USERNAME` | `root` | Utilisateur SSH (compte root sur le VPS) |
| `VPS_PASSWORD` | `***` | Mot de passe SSH root |
| `VPS_PORT` | `22` | Port SSH (optionnel, 22 par défaut) |
| `DEPLOY_PATH` | `/var/www/bengadok/bengadok` | Chemin absolu de l'app sur le VPS |

> **Note** : le déploiement GitHub Actions se connecte en **root** via mot de passe (`VPS_PASSWORD`).

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

### Cloner le dépôt sur le VPS (en root)

```bash
mkdir -p /var/www/bengadok
cd /var/www/bengadok
git clone git@github.com:TON_USER/bengadok.git bengadok
cd /var/www/bengadok/bengadok

# Configurer le .env (UNE SEULE FOIS — ne jamais commiter)
cp .env.example .env
nano .env
php artisan key:generate
php artisan storage:link
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=UserSeeder --force   # premier déploiement seulement

# Permissions storage
chmod -R 775 storage bootstrap/cache
chown -R root:www-data storage bootstrap/cache
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

    root /var/www/bengadok/bengadok/public;
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

## 4. Workers queue + Reverb (Supervisor)

```bash
apt install supervisor
```

```ini
# /etc/supervisor/conf.d/bengadok-worker.conf
[program:bengadok-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bengadok/bengadok/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bengadok-worker.log
stopwaitsecs=3600
```

```ini
# /etc/supervisor/conf.d/bengadok-reverb.conf
[program:bengadok-reverb]
command=php /var/www/bengadok/bengadok/artisan reverb:start
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bengadok-reverb.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start bengadok-worker:* bengadok-reverb
supervisorctl status
```

> **Obligatoire pour le déploiement** : Supervisor doit être installé et les programmes `bengadok-worker` / `bengadok-reverb` actifs. Le workflow CI échoue si `supervisorctl` est absent ou si le redémarrage des workers échoue.

---

## 5. Accès SSH pour le déploiement GitHub Actions

Le workflow CI se connecte en **root** avec le mot de passe défini dans le secret `VPS_PASSWORD`.

Sur le VPS, vérifier que la connexion root par SSH est autorisée (à n'autoriser que depuis des IP de confiance si possible) :

```bash
# /etc/ssh/sshd_config — si besoin
PermitRootLogin yes

systemctl reload sshd
```

Test depuis votre machine :

```bash
ssh root@TON_VPS
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
