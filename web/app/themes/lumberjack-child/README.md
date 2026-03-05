# Guide du Thème Lumberjack pour Débutants

Ce guide vous explique comment fonctionne le thème Lumberjack et comment le personnaliser. Vous vous concentrerez principalement sur deux dossiers : **`assets/`** et **`views/`**.

---

## Table des matières

1. [Structure du thème](#structure-du-thème)
2. [Le dossier `assets/` - Styles et Scripts](#le-dossier-assets---styles-et-scripts)
3. [Le dossier `views/` - Templates Twig](#le-dossier-views---templates-twig)
4. [Les Controllers - Logique PHP](#les-controllers---logique-php)
5. [Commandes NPM - Compiler vos modifications](#commandes-npm---compiler-vos-modifications)
6. [Workflow de travail recommandé](#workflow-de-travail-recommandé)

---

## Structure du thème

```
lumberjack/
├── assets/          ← VOS STYLES ET SCRIPTS (SCSS, JS, images)
├── views/           ← VOS TEMPLATES HTML (fichiers Twig)
├── dist/            ← Fichiers compilés (ne pas modifier directement)
├── app/             ← Classes PHP avancées
├── config/          ← Configuration du thème
├── *.php            ← Controllers (archive.php, single.php, etc.)
├── functions.php    ← Point d'entrée PHP
├── package.json     ← Dépendances et scripts NPM
└── vite.config.mjs  ← Configuration du build
```

### Ce que vous devez retenir :

- **`assets/`** : C'est ici que vous écrivez vos styles CSS (SCSS) et votre JavaScript
- **`views/`** : C'est ici que vous modifiez le HTML des pages (templates Twig)
- **`dist/`** : Ne modifiez JAMAIS ce dossier, il est généré automatiquement

---

## Le dossier `assets/` - Styles et Scripts

C'est ici que vous personnalisez l'apparence visuelle du site.

### Structure

```
assets/
├── scss/
│   └── main.scss    ← Votre fichier CSS principal
├── js/
│   └── main.js      ← Votre JavaScript principal
├── img/             ← Vos images
│   ├── 404.svg
│   └── whoops.svg
└── icons.svg        ← Icônes SVG du site
```

### Modifier les styles CSS

Ouvrez `assets/scss/main.scss` pour modifier les styles :

```scss
// Variables - Modifiez ces valeurs pour changer les couleurs du site
$primary-color: #0073aa; // Couleur principale
$secondary-color: #23282d; // Couleur secondaire
$text-color: #333; // Couleur du texte
$background-color: #fff; // Couleur de fond
$font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;

// Vos styles personnalisés
body {
    font-family: $font-family;
    color: $text-color;
    background-color: $background-color;
}

// Ajoutez vos propres styles ici...
```

### Ajouter du JavaScript

Ouvrez `assets/js/main.js` pour ajouter vos scripts :

```javascript
// Exemple : Ajouter une interaction au clic
document.addEventListener("DOMContentLoaded", function () {
    console.log("Le site est chargé !");

    // Votre code JavaScript ici...
});
```

### Ajouter des images

1. Placez vos images dans `assets/img/`
2. Référencez-les dans vos templates Twig :
    ```twig
    <img src="{{ function('get_template_directory_uri') }}/assets/img/mon-image.jpg" alt="Description">
    ```

---

## Le dossier `views/` - Templates Twig

Les fichiers Twig remplacent le PHP traditionnel pour écrire le HTML. C'est plus lisible et plus sécurisé.

### Structure

```
views/
├── base.twig                 ← Template maître (structure HTML de base)
├── components/
│   └── footer.twig           ← Composant footer réutilisable
└── templates/
    ├── home.twig             ← Page d'accueil
    ├── posts.twig            ← Liste des articles (archives)
    ├── generic-page.twig     ← Pages et articles individuels
    └── errors/
        ├── 404.twig          ← Page erreur 404
        └── whoops.twig       ← Page erreur 500
```

### Le template de base (`base.twig`)

C'est le squelette HTML de toutes vos pages :

```twig
<!DOCTYPE html>
<html {{ function('language_attributes') }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ site.name }} - {{ post.title }}</title>
    {{ function('wp_head') }}
</head>
<body {{ function('body_class') }}>

    <header>
        <!-- Votre header ici -->
    </header>

    <main>
        {% block content %}
            <!-- Le contenu des pages s'affiche ici -->
        {% endblock %}
    </main>

    {% include 'components/footer.twig' %}

    {{ function('wp_footer') }}
</body>
</html>
```

### Héritage de templates

Les autres templates "étendent" le template de base :

```twig
{# home.twig #}
{% extends "base.twig" %}

{% block content %}
    <h1>Bienvenue sur {{ site.name }}</h1>
    <p>Ceci est ma page d'accueil personnalisée</p>
{% endblock %}
```

### Syntaxe Twig - Les bases

| Syntaxe | Utilisation             | Exemple                         |
| ------- | ----------------------- | ------------------------------- |
| `{{ }}` | Afficher une variable   | `{{ post.title }}`              |
| `{% %}` | Logique (if, for, etc.) | `{% if posts %}`                |
| `{# #}` | Commentaires            | `{# Ceci est un commentaire #}` |

### Variables courantes

```twig
{{ site.name }}           {# Nom du site #}
{{ site.url }}            {# URL du site #}
{{ post.title }}          {# Titre de l'article/page #}
{{ post.content }}        {# Contenu de l'article/page #}
{{ post.link }}           {# URL de l'article/page #}
{{ post.thumbnail.src }}  {# Image à la une #}
```

### Boucles et conditions

```twig
{# Boucle sur les articles #}
{% for post in posts %}
    <article>
        <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
        <p>{{ post.preview }}</p>
    </article>
{% endfor %}

{# Condition #}
{% if posts is not empty %}
    {# Afficher les articles #}
{% else %}
    <p>Aucun article trouvé.</p>
{% endif %}
```

### Appeler des fonctions WordPress

```twig
{{ function('wp_head') }}                           {# Inclure le head WordPress #}
{{ function('wp_footer') }}                         {# Inclure le footer WordPress #}
{{ function('get_template_directory_uri') }}        {# URL du thème #}
{{ function('the_custom_logo') }}                   {# Logo personnalisé #}
```

---

## Les Controllers - Logique PHP

Les controllers font le lien entre WordPress (l'admin et base de données) et vos templates Twig. Ils récupèrent les données et les passent aux templates.

### Liste des controllers

| Fichier       | Quand est-il utilisé ?                | Template Twig associé         |
| ------------- | ------------------------------------- | ----------------------------- |
| `index.php`   | Page d'accueil (si pas de front-page) | `templates/posts.twig`        |
| `archive.php` | Archives (catégories, tags, dates)    | `templates/posts.twig`        |
| `single.php`  | Article individuel                    | `templates/generic-page.twig` |
| `page.php`    | Page individuelle                     | `templates/generic-page.twig` |
| `search.php`  | Résultats de recherche                | `templates/posts.twig`        |
| `author.php`  | Page auteur                           | `templates/posts.twig`        |
| `404.php`     | Page non trouvée                      | `templates/errors/404.twig`   |

### Anatomie d'un controller

```php
<?php
// archive.php

use App\Http\Controllers\Controller;
use Rareloop\Lumberjack\Http\Responses\TimberResponse;
use Rareloop\Lumberjack\Post;
use Timber\Timber;

class ArchiveController extends Controller
{
    public function handle()
    {
        // 1. Récupérer le contexte WordPress
        $context = Timber::get_context();

        // 2. Ajouter des données personnalisées
        $context['posts'] = Post::all();
        $context['title'] = 'Nos Articles';

        // 3. Retourner le template avec les données
        return new TimberResponse('templates/posts.twig', $context);
    }
}
```

### Récupérer des articles

```php
// Tous les articles
$context['posts'] = Post::all();

// Les 5 derniers articles publiés
$context['posts'] = Post::whereStatus('publish')
    ->limit(5)
    ->get();

// Articles d'une catégorie
$context['posts'] = Post::whereCategory('actualites')
    ->get();
```

### Passer des données au template

Tout ce que vous ajoutez à `$context` devient disponible dans Twig :

```php
// Dans le controller
$context['mon_titre'] = 'Bienvenue !';
$context['annee'] = date('Y');
```

```twig
{# Dans le template Twig #}
<h1>{{ mon_titre }}</h1>
<p>Nous sommes en {{ annee }}</p>
```

---

## Commandes NPM - Compiler vos modifications

Quand vous modifiez les fichiers dans `assets/`, vous devez les compiler pour qu'ils soient utilisables.

### Installation des dépendances (une seule fois)

```bash
# Dans le dossier du thème
cd web/app/themes/lumberjack-child
npm install
```

### Mode développement (recommandé pendant que vous travaillez)

```bash
npm run dev
```

Cette commande :

- Compile vos fichiers SCSS en CSS
- Compile votre JavaScript
- **Surveille les modifications** et recompile automatiquement

Laissez cette commande tourner dans votre terminal pendant que vous travaillez.

### Build de production

```bash
npm run build
```

Utilisez cette commande une fois que vous avez terminé, avant de mettre en ligne.

### Où vont les fichiers compilés ?

```
assets/scss/main.scss  →  dist/css/style.css
assets/js/main.js      →  dist/js/main.js
```

---

## Workflow de travail recommandé

### 1. Démarrer le développement

```bash
cd web/app/themes/lumberjack-child
npm run dev
```

### 2. Modifier les styles

Éditez `assets/scss/main.scss` → Sauvegardez → Actualisez le navigateur

### 3. Modifier les templates

Éditez les fichiers dans `views/` → Sauvegardez → Actualisez le navigateur

### 4. Ajouter du JavaScript

Éditez `assets/js/main.js` → Sauvegardez → Actualisez le navigateur

### 5. Terminer

```bash
npm run build
```

---

## Récapitulatif - Où modifier quoi ?

| Je veux...                                | Fichier à modifier                                  |
| ----------------------------------------- | --------------------------------------------------- |
| Changer les couleurs                      | `assets/scss/main.scss`                             |
| Modifier le CSS                           | `assets/scss/main.scss`                             |
| Ajouter du JavaScript                     | `assets/js/main.js`                                 |
| Modifier le header/footer                 | `views/base.twig` ou `views/components/footer.twig` |
| Modifier la page d'accueil                | `views/templates/home.twig`                         |
| Modifier l'affichage des articles         | `views/templates/posts.twig`                        |
| Modifier une page/article seul            | `views/templates/generic-page.twig`                 |
| Changer les données passées à un template | `archive.php`, `single.php`, etc.                   |

---

## Ressources utiles

- [Documentation Twig](https://twig.symfony.com/doc/3.x/)
- [Documentation Timber](https://timber.github.io/docs/)
- [Documentation Lumberjack](https://docs.lumberjack.rareloop.com)

---

Bon courage dans votre apprentissage !
