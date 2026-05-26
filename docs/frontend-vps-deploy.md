# Frontend VPS Deploy

Architecture recommandee :

- backend Laravel : `https://seo.amiantix.com`
- frontend client Next.js : `https://app.amiantix.com`
- reverse proxy Nginx : `app.amiantix.com -> 127.0.0.1:3000`

## 1. Preparer le dossier sur le VPS

Exemple :

```bash
mkdir -p /var/www/seo-engine
cd /var/www/seo-engine
git clone https://github.com/ofyreagency-arch/amiantix_seo.git .
cd /var/www/seo-engine/frontend
```

Si le repo est deja present sur le VPS :

```bash
cd /var/www/seo-engine
git pull
cd /var/www/seo-engine/frontend
```

## 2. Installer Node.js et dependances

Exemple Node 22 :

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
node -v
npm -v
```

Puis :

```bash
cd /var/www/seo-engine/frontend
npm ci
```

## 3. Variables d environnement

Creer :

```bash
cp .env.production.example .env.production
```

Contenu minimal :

```env
PRAEVISEO_API_URL=https://seo.amiantix.com
NEXT_PUBLIC_API_URL=https://seo.amiantix.com
NODE_ENV=production
PORT=3000
```

## 4. Build de production

```bash
cd /var/www/seo-engine/frontend
npm run build
```

## 5. Lancer en fond avec Supervisor

Copier le template :

```bash
cp /var/www/seo-engine/deploy/supervisor/praeviseo-frontend.conf /etc/supervisor/conf.d/
supervisorctl reread
supervisorctl update
supervisorctl start praeviseo-frontend
supervisorctl status
```

Etat attendu :

```text
praeviseo-frontend RUNNING
```

## 6. Publier avec Nginx

Copier le template :

```bash
cp /var/www/seo-engine/deploy/nginx/praeviseo-frontend.conf /etc/nginx/sites-available/praeviseo-frontend
ln -s /etc/nginx/sites-available/praeviseo-frontend /etc/nginx/sites-enabled/praeviseo-frontend
nginx -t
systemctl reload nginx
```

Ensuite brancher le SSL, par exemple avec Certbot.

## 7. Verification

Verifier le process :

```bash
supervisorctl status
tail -n 50 /var/log/supervisor/praeviseo-frontend.log
```

Verifier localement sur le VPS :

```bash
curl -I http://127.0.0.1:3000
```

Verifier publiquement :

```bash
curl -I https://app.amiantix.com
```

## 8. Mise a jour

```bash
cd /var/www/seo-engine
git pull
cd /var/www/seo-engine/frontend
npm ci
npm run build
supervisorctl restart praeviseo-frontend
```

## 9. Points importants

- Le frontend reste separe du backend Laravel.
- Le frontend parle au backend via `PRAEVISEO_API_URL`.
- Le client ouvre seulement `https://app.amiantix.com`.
- Le backend continue a vivre sur `https://seo.amiantix.com`.
