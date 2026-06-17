# Déploiement sur Railway

Guide pour déployer cette application Symfony 7 sur [Railway](https://railway.app).

---

## Prérequis

- Un compte Railway (gratuit)
- Le dépôt Git poussé sur GitHub ou GitLab

---

## Étapes de déploiement

### 1. Créer un nouveau projet Railway

Dans le dashboard Railway :

1. **New Project** → **Deploy from GitHub repo**
2. Sélectionner le dépôt `task-manager`
3. Railway détecte automatiquement PHP via Nixpacks

---

### 2. Configurer la commande de build

Dans **Settings → Build** du service, renseigner :

```
bash deploy.sh
```

Ce script exécute dans l'ordre :

```bash
composer install --no-dev --optimize-autoloader   # dépendances de production uniquement
php bin/console cache:clear --env=prod            # vide le cache Symfony
php bin/console doctrine:migrations:migrate       # applique la migration SQLite
```

---

### 3. Vérifier le Procfile

Le fichier `Procfile` à la racine du projet indique à Railway comment démarrer le serveur :

```
web: php -S 0.0.0.0:$PORT -t public/
```

`$PORT` est injecté automatiquement par Railway. Il n'y a rien à modifier.

---

### 4. Configurer les variables d'environnement

Dans **Settings → Variables**, ajouter les variables suivantes :

| Variable | Valeur | Description |
|---|---|---|
| `APP_ENV` | `prod` | Active le mode production Symfony |
| `APP_SECRET` | `<clé aléatoire 32 caractères>` | Clé de chiffrement des sessions |
| `DATABASE_URL` | `sqlite:///%kernel.project_dir%/var/app.db` | Chemin vers la base SQLite |

**Générer une valeur pour `APP_SECRET` :**

```bash
# En local, dans le terminal
php -r "echo bin2hex(random_bytes(16));"
```

> **Important** : ne jamais committer `APP_SECRET` dans le dépôt Git.

---

### 5. Lancer le déploiement

Cliquer sur **Deploy** (ou pousser un commit sur la branche principale). Railway exécute automatiquement :

1. `bash deploy.sh` (phase de build)
2. `php -S 0.0.0.0:$PORT -t public/` (démarrage du serveur)

L'URL publique est disponible dans **Settings → Networking → Public Domain**.

---

## SQLite sur Railway — point important

Railway utilise un **filesystem éphémère** : le contenu de `var/app.db` est **réinitialisé à chaque redéploiement**. Cela signifie que toutes les tâches créées sont perdues lors d'un nouveau déploiement.

### Option A — Accepter la limitation (démo)

Pour une démonstration ou un exercice technique, ce comportement est acceptable. La base est recréée proprement à chaque déploiement grâce à la migration.

### Option B — Volume persistant Railway (données conservées)

Pour conserver les données entre les déploiements :

1. Dans le dashboard Railway, aller dans **Settings → Volumes**
2. Créer un volume monté sur `/app/var`
3. Mettre à jour `DATABASE_URL` pour pointer vers ce volume :

```
DATABASE_URL=sqlite:////app/var/app.db
```

Le répertoire `/app/var` est alors persistant entre les redéploiements.

---

## Vérifier que tout fonctionne

Une fois le déploiement terminé, tester l'API avec curl (remplacer `<url>` par le domaine Railway) :

```bash
# Lister les tâches (doit retourner [])
curl https://<url>/api/tasks

# Créer une tâche
curl -X POST https://<url>/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"titre": "Test Railway", "statut": "todo"}'

# Documentation Swagger
# Ouvrir dans le navigateur : https://<url>/api/doc
```

---

## Résumé des fichiers de déploiement

| Fichier | Rôle |
|---|---|
| `Procfile` | Commande de démarrage du serveur web (`php -S`) |
| `deploy.sh` | Script de build : composer, cache, migrations |
| `.env.example` | Template des variables d'environnement à copier |
