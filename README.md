# WordPress Boilerplate - Bedrock + Lumberjack

Ce projet est un template pour créer des sites WordPress modernes avec une architecture professionnelle. Il utilise [Bedrock](https://roots.io/bedrock/) pour la structure du projet et [Lumberjack](https://lumberjack.rareloop.com/) comme framework de thème.

---

## Prérequis

Avant de commencer, assurez-vous d'avoir installé sur votre machine :

- **[Docker Desktop](https://www.docker.com/products/docker-desktop/)** - Pour faire tourner l'environnement de développement (base de données, serveur web, PHP)
- **Un éditeur de code** - VS Code recommandé

---

## Installation

### 1. Cloner le projet

```bash
git clone <url-du-repo> mon-projet
cd mon-projet
```

### 2. Configurer l'environnement

Dupliquez le fichier `.env.sample` et renommez-le en `.env` :

```bash
cp .env.sample .env
```

### 3. Modifier le fichier `.env`

Le fichier `.env` contient toutes les variables de configuration de votre projet. **Ce fichier ne doit jamais être commité** car il contient des informations sensibles (mots de passe, clés secrètes).

Voici les variables à configurer :

#### Base de données

```env
DB_NAME=mon_projet          # Nom de votre base de données
DB_USER=user                # Utilisateur de la base de données -> en local, vous pouvez laisser tel quel
DB_PASSWORD=password        # Mot de passe de la base de données -> en local, vous pouvez laisser tel quel
DB_ROOT_PASSWORD=rootpassword  # Mot de passe root MySQL -> en local, vous pouvez laisser tel quel
DB_HOST=db                  # Laisser "db" (nom du service Docker)
```

#### Configuration WordPress

```env
WP_ENV=development          # Environnement : development, staging ou production
WP_HOME=http://localhost:8000       # URL de votre site
WP_SITEURL=http://localhost:8000/wp # URL de WordPress (avec /wp)
```

#### Clés de sécurité

Les clés `AUTH_KEY`, `SECURE_AUTH_KEY`, etc. sont des clés de chiffrement pour WordPress. Vous pouvez générer de nouvelles clés uniques sur : https://roots.io/salts.html

### 4. Lancer Docker

```bash
# Construire les images Docker
docker compose build

# Démarrer les conteneurs
docker compose up -d

# Installer les dépendances ccomposer
docker compose exec wordpress composer install
```

### 5. Accéder au site

Une fois Docker lancé, vous avez accès à :

| Service         | URL                               | Description                            |
| --------------- | --------------------------------- | -------------------------------------- |
| Site WordPress  | http://localhost:8000             | Votre site                             |
| Admin WordPress | http://localhost:8000/wp/wp-admin | Back-office                            |
| phpMyAdmin      | http://localhost:8080             | Gestion de la base de données          |
| Mailhog         | http://localhost:8025             | Intercepteur d'emails (pour les tests) |

---

## Structure du projet

```
├── web/                    # Racine web (DocumentRoot)
│   ├── wp/                 # WordPress core (installé via Composer)
│   ├── app/
│   │   ├── mu-plugins/     # Must-use plugins
│   │   ├── plugins/        # Plugins WordPress
│   │   ├── themes/         # Thèmes
│   │   │   └── lumberjack/ # Thème Lumberjack de base
│   │   └── uploads/        # Fichiers uploadés
│   └── index.php           # Point d'entrée
├── config/                 # Configuration Bedrock
├── composer.json           # Dépendances PHP
├── docker-compose.yml      # Configuration Docker
├── Dockerfile              # Image PHP personnalisée
└── .env                    # Variables d'environnement (à créer)
```

---

## Ajouter un thème custom Lumberjack

Lorsque vous créez votre propre thème basé sur Lumberjack avec des assets frontend (CSS/JS compilés avec Vite, TailwindCSS, etc.), vous devez modifier le `Dockerfile` pour que les assets soient compilés lors du build Docker.

### Étapes :

1. **Créez votre thème** dans `web/app/themes/`

Conseil: dupliquer le thème `lumberjack` et renommer-le.

Une fois votre thème nommé, faites-une recherche dans tous les fichiers du projet (`Cmd + Maj + F`) et remplacer `lumberjack-child` par le nom de votre thème.

2. Dans le fichier style.css de votre theme, renommer le `Theme Name` (ligne 2)

3. **Ouvrez le `Dockerfile`** et décommentez les lignes 39 à 48 :

```dockerfile
# Définir le répertoire de travail du thème
WORKDIR /var/www/html/web/app/themes/lumberjack-child

# Copier les fichiers frontend pour l'installation JS
COPY web/app/themes/lumberjack-child/package.json ./
COPY web/app/themes/lumberjack-child/package-lock.json ./
COPY web/app/themes/lumberjack-child/vite.config.mjs ./
COPY web/app/themes/lumberjack-child/assets/ ./assets

# Installer les dépendances JS + build
RUN npm install && npm run build
```

4. **Reconstruisez l'image Docker** :

```bash
docker compose build
docker compose up -d
```

5. **Rendez vous dans le back-office (http://localhost:8000/wp/wp-admin/)** :

Dans _Apparences_ > _Thèmes_ > Sélectionner votre thème

### Développement du thème en local

Pour travailler sur les assets de votre thème en local (avec hot-reload) :

```bash
# Se placer dans le dossier du thème
cd web/app/themes/lumberjack-child

# Installer les dépendances
npm install

# Lancer le mode développement (watch)
npm run dev
```

Pour plus d'informations, lisez le README.md spécifique dans le thème.

---

## Commandes utiles

```bash
# Arrêter les conteneurs
docker compose down

# Reconstruire après modification du Dockerfile
docker compose build --no-cache

# Exécuter Composer dans le conteneur
docker compose exec wordpress composer install

# Voir les logs des conteneurs
docker compose logs -f

# Exécuter WP-CLI
docker compose exec wordpress wp --allow-root <commande>
```

---

## GitHub Actions - Déploiement automatique

Ce projet inclut une **GitHub Action** qui déploie automatiquement votre code sur un serveur distant à chaque push.

### Fonctionnement

| Branche | Environnement |
| ------- | ------------- |
| `main`  | Production    |
| `dev`   | Staging       |

### Ce que fait la GitHub Action

1. Installe PHP 8.2 et Node.js 20
2. Installe les dépendances Composer (`composer install`)
3. Installe les dépendances npm du thème (`npm install`)
4. Compile les assets du thème (`npm run build`)
5. Déploie les fichiers via `rsync` sur le serveur

### Configuration requise

Pour utiliser le déploiement automatique, vous devez configurer des **secrets** dans votre repository GitHub :

1. Allez dans **Settings > Secrets and variables > Actions**
2. Cliquez sur **New repository secret**
3. Ajoutez les secrets suivants :

| Secret                | Description                       | Exemple                                |
| --------------------- | --------------------------------- | -------------------------------------- |
| `SSH_PRIVATE_KEY`     | Clé SSH privée (format ED25519)   | Contenu du fichier `~/.ssh/id_ed25519` |
| `REMOTE_USER`         | Utilisateur SSH du serveur        | `deploy`                               |
| `REMOTE_HOST`         | Adresse du serveur                | `monsite.com`                          |
| `REMOTE_PATH_PROD`    | Chemin vers le dossier production | `/home/web/production/`                |
| `REMOTE_PATH_STAGING` | Chemin vers le dossier staging    | `/home/web/staging/`                   |

> N'oubliez pas d'ajouter la **clé publique SSH** correspondante (`id_ed25519.pub`) dans le fichier `~/.ssh/authorized_keys` de votre serveur.

### Modifier le chemin du thème

Dans le fichier `.github/workflows/deploy.yml`, modifiez la variable `THEME_PATH` avec le nom de votre thème :

```yaml
env:
  THEME_PATH: web/app/themes/lumberjack-child # Remplacez par votre thème
```

### Fichiers exclus du déploiement

Les fichiers suivants ne sont **pas** déployés (ils restent sur le serveur) :

- `.env` et fichiers de configuration locale (à créer manuellement sur le serveur)
- `node_modules/` et `vendor/`
- `web/app/uploads/` (pour ne pas écraser les médias en ligne)
- Fichiers Docker (`docker-compose.yml`, `Dockerfile`, etc.)
- Fichiers de développement (`.git/`, `*.md`, `tests/`)

---

## Résolution de problèmes

### Erreur Docker "docker-credential-desktop not found"

Éditez le fichier `~/.docker/config.json` et videz la valeur de `credsStore` :

```json
{
  "credsStore": ""
}
```

### La base de données ne se connecte pas

Vérifiez que `DB_HOST=db` dans votre fichier `.env` (c'est le nom du service Docker, pas `localhost`).

### Les assets ne se compilent pas

1. Vérifiez que vous avez décommenté les lignes du `Dockerfile`
2. Remplacez `lumberjack-child` par le nom exact de votre thème
3. Reconstruisez l'image : `docker compose build --no-cache`

### Erreur de permissions sur uploads

```bash
docker compose exec wordpress chown -R www-data:www-data /var/www/html/web/app/uploads
```

### Le site affiche une erreur 502

Attendez quelques secondes que tous les conteneurs démarrent, puis rafraîchissez la page.

---

## Ressources

- [Documentation Bedrock](https://roots.io/bedrock/docs/)
- [Documentation Lumberjack](https://lumberjack.rareloop.com/docs/1.x/)
- [Documentation Docker](https://docs.docker.com/)
- [TailwindCSS](https://tailwindcss.com/docs)
- [Vite](https://vitejs.dev/guide/)
