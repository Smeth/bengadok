# Analyse des vues – Module Utilisateur Backoffice

Comparaison des maquettes fournies avec les fonctionnalités déjà mises en place.

---

## 1. Vue « Liste des utilisateurs backoffice » (page actuelle)

### Maquette
- **Emplacement** : `Utilisateurs Backoffice` dans la navigation
- **Contenu** : Cartes d’utilisateurs avec nom, rôle, permissions
- **Fonctionnalités** : Recherche, bouton « Nouvel Utilisateur », actions Modifier / Détails / Supprimer

### Implémenté
| Élément | Statut | Détails |
|--------|--------|---------|
| Page liste | ✅ | `resources/js/pages/Utilisateurs/Index.vue` |
| Cartes utilisateurs | ✅ | Cartes avec nom, email, rôle, permissions (3 visibles + « +N autres ») |
| Recherche | ✅ | Champ « Rechercher un utilisateur... » avec recherche en temps réel |
| Bouton création | ✅ | « Nouvel Utilisateur » |
| Modal création | ✅ | Nom, email, username, mot de passe, choix du rôle (Super Admin, Admin, Agent) |
| Modal modification | ✅ | Nom, email, username, rôle |
| Actions par carte | ✅ | Modifier (icône crayon), Supprimer (icône poubelle) |
| Bouton « Gestion accès » | ⚠️ | Présent mais non fonctionnel (icône cadenas) |
| Bouton « Détails » | ❌ | Pas d’équivalent (on a directement Modifier) |

### À compléter
1. **Bouton « Détails »** : Page ou modal de détail d’un utilisateur (profil, rôle, permissions, historique)
2. **Gestion accès** : Modification des permissions d’un utilisateur (hors changement de rôle global)

---

## 2. Vue « Gestion des rôles » (Réglages)

### Maquette 1 – Liste des rôles
- **Emplacement** : Sous `Réglages` ou section dédiée
- **Onglets** :
  - « Rôles agents pharmacie » : Gérant, Vendeur
  - « Rôles Utilisateurs Backoffice » : Super Admin, Admin, Agent
- **Contenu** : Cartes de rôles avec nombre de permissions, description, liste des permissions
- **Actions** : Modifier, Détails, Supprimer, « + Nouveau Rôle »

### Implémenté
| Élément | Statut | Détails |
|--------|--------|---------|
| Page gestion des rôles | ❌ | Non implémentée |
| Onglets rôles pharmacie / backoffice | ❌ | Non implémenté |
| Cartes de rôles | ❌ | Non implémenté |
| CRUD rôles | ❌ | Les rôles viennent du `RolePermissionSeeder` uniquement |

### À développer (entier)
1. **Page ou section « Gestion des rôles »**
   - Route : `/settings/roles` ou `/utilisateurs/roles`
   - Restriction : Super Admin uniquement (`LISTE_ACCES_PAR_ROLE.md`)

2. **Onglets**
   - Rôles agents pharmacie (gerant, vendeur)
   - Rôles Utilisateurs Backoffice (super_admin, admin, agent_call_center)

3. **Liste des rôles**
   - Cartes avec : nom, nombre de permissions, description
   - Recherche de rôles
   - Bouton « + Nouveau Rôle »

4. **Actions**
   - Modifier
   - Détails
   - Supprimer (avec garde-fous pour les rôles système)

---

## 3. Modale « Créer un rôle »

### Maquette
- Champs : **Libellé**, **Description**
- **Permissions** groupées avec toggles :
  - **Commandes** : Gérer les commandes, Consulter les statistiques
  - **Pharmacie** : Gestion des pharmacies, Rôles pharmacies
  - **Médicaments** : Consulter catalogue Médicaments, Consulter statistiques Médicaments

### Implémenté
| Élément | Statut |
|--------|--------|
| Modal création rôle | ❌ |
| Permissions par catégorie | ❌ |
| Toggles de permissions | ❌ |

### À développer
1. Modal « Créer un rôle » avec :
   - Libellé
   - Description
   - Permissions groupées par catégorie (Commandes, Pharmacie, Médicaments, Clients, Utilisateurs backoffice)
   - Boutons Annuler / Créer le rôle

2. **Backend** : nouveau `RoleController` ou extension du contrôleur existant
   - `POST /roles` pour créer un rôle
   - Mapping libellé → `name` en base (ex. : « agent call-center » → `agent_call_center`)

3. **Alignement des permissions** :  
   Le `RolePermissionSeeder` définit déjà les permissions ; l’UI doit proposer une sélection alignée avec ces noms techniques.

---

## 4. Modale « Détails du rôle »

### Maquette (ex. Super Admin)
- Nom du rôle
- **Utilisateurs** : nombre d’utilisateurs avec ce rôle (ex. : 2)
- **Permissions** : nombre (ex. : 10) et liste détaillée par catégorie :
  - Commandes
  - Pharmacie
  - Médicaments
  - Clients
  - Utilisateurs backoffice

### Implémenté
| Élément | Statut |
|--------|--------|
| Modal détail rôle | ❌ |
| Comptage des utilisateurs par rôle | ❌ |
| Affichage des permissions par catégorie | ❌ |

### À développer
1. Modal « Détails du rôle » accessible via le bouton « Détails » sur une carte
2. Affichage :
   - Nom du rôle
   - Nombre d’utilisateurs assignés
   - Liste des permissions, groupées par catégorie
3. Optionnel : lien vers la liste des utilisateurs ayant ce rôle

---

## 5. Structure des permissions (référence)

### Permissions en base (`RolePermissionSeeder`)

| Catégorie | Permission (nom technique) | Label UI |
|-----------|---------------------------|----------|
| **Commandes** | gérer-commandes | Gérer les commandes |
| | stats-commandes-pharmacie | Consulter statistiques commandes |
| **Pharmacie** | gérer-pharmacies | Gestion des pharmacies |
| | gérer-rôles-pharmacies | Rôles pharmacies |
| **Médicaments** | gérer-catalogue | Consulter catalogue Médicaments |
| | stats-médicaments | Consulter statistiques Médicaments |
| **Clients** | gérer-clients | Consulter la liste des clients |
| | gérer-doublons | Gérer les doublons clients |
| | stats-clients | Consulter les statistiques clients |
| **Utilisateurs backoffice** | gérer-rôles-backoffice | Gérer les rôles du backoffice |
| **Super Admin** | gérer-tout | Gérer tout |
| **Agent** | reception-commande, recherche-prix-pharmacie, etc. | - |

---

## 6. Plan de complétion recommandé

### Priorité 1 – Gestion des rôles (Super Admin)
1. Créer la route `GET /settings/roles` ou `GET /utilisateurs/roles` (Super Admin)
2. Page avec onglets Rôles pharmacie / Rôles backoffice
3. Cartes de rôles, recherche, bouton « + Nouveau Rôle »
4. Modales : Créer un rôle, Détails du rôle, Modifier un rôle

### Priorité 2 – Contrôleur rôles (backend)
1. `RoleController` avec : index, store, update, destroy, show
2. Intégration avec Spatie (`Role`, `Permission`)
3. Vérification Super Admin pour toutes les actions

### Priorité 3 – Ajustements utilisateurs
1. Bouton « Détails » sur les cartes utilisateurs
2. Gestion accès : modal ou page pour ajuster les permissions d’un utilisateur
3. Intégration d’un lien « Gestion des rôles » dans les Réglages (Super Admin uniquement)

---

## 7. Récapitulatif : implémenté vs à faire

| Vue / Fonctionnalité | Implémenté | À faire |
|----------------------|:----------:|:-------:|
| Liste utilisateurs backoffice | ✅ | – |
| Cartes utilisateurs avec permissions | ✅ | – |
| Recherche utilisateurs | ✅ | – |
| Modal créer utilisateur + rôle | ✅ | – |
| Modal modifier utilisateur | ✅ | – |
| Supprimer utilisateur | ✅ | – |
| Détail utilisateur | ❌ | ✅ |
| Gestion accès (permissions fine) | ❌ | ✅ |
| Page gestion des rôles | ❌ | ✅ |
| Onglets rôles pharmacie / backoffice | ❌ | ✅ |
| Cartes de rôles | ❌ | ✅ |
| Modal créer un rôle | ❌ | ✅ |
| Modal détails rôle | ❌ | ✅ |
| Modal modifier rôle | ❌ | ✅ |
