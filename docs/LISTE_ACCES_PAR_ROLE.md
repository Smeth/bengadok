# Liste des accès par rôle – BengaDok

## Rôles

| Rôle | Description |
|------|-------------|
| **Super Admin** | Accès complet, gestion des rôles |
| **Admin** | Gestion backoffice (sans gestion des rôles) |
| **Agent Call Center** | Gestion des commandes, réceptions, clients |
| **Gérant pharmacie** | Gestion de sa pharmacie, vendeurs, historiques |
| **Vendeur** | Interface Dok Pharma pour traiter les commandes |

---

## Tableau récapitulatif des accès

| Module / Fonctionnalité | Super Admin | Admin | Agent Call Center | Gérant | Vendeur |
|------------------------|:-----------:|:-----:|:-----------------:|:------:|:-------:|
| **Tableau de bord** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Créer une commande** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Liste des commandes** | ✅ (toutes) | ✅ (toutes) | ✅ (via Agent) | ✅ (sa pharmacie) | ❌ |
| **Détail commande** | ✅ | ✅ | ✅ | ✅ (sa pharmacie) | ✅ (sa pharmacie) |
| **Valider / Modifier statut** | ✅ | ✅ | ✅ | ✅ (sa pharmacie) | ❌ |
| **Valider disponibilité produits** | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Valider retrait livreur** | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Renvoyer à 2ème pharmacie** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Pharmacies** | ✅ CRUD | ✅ CRUD | ❌ | ❌ | ❌ |
| **Médicaments** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Clients** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Doublons clients** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Utilisateurs Backoffice** | ✅ CRUD | ✅ CRUD | ❌ | ❌ | ❌ |
| **Gérer rôles** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Dok Pharma** | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Historique pharmacie** | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Créer vendeurs (pharmacie)** | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Réglages / Profil** | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Détail par rôle

### Super Admin
- Tout ce que l’Admin peut faire
- Gestion des rôles pharmacie (gérant, vendeur)
- Gestion des rôles backoffice (admin, agent)
- Création / modification / suppression d’utilisateurs backoffice

### Admin
- Création de commandes (backoffice et flux agent)
- Liste et détail de toutes les commandes
- Modification des statuts, acceptation client
- Gestion des pharmacies (CRUD)
- Gestion du catalogue médicaments
- Gestion des clients et doublons
- Gestion des utilisateurs backoffice (sans rôles)
- Aucun accès à Dok Pharma ni à la création de vendeurs en pharmacie

### Agent Call Center
- Nouvelle commande (flux agent)
- Mes réceptions
- Clients (consultation)
- Création de commandes via `/agent/nouvelle-commande` et `/agent/commande`
- Renvoyer une commande vers une 2ᵉ pharmacie
- Accès à la liste commandes et détail (côté backoffice)
- Pas d’accès : pharmacies, médicaments, doublons, utilisateurs backoffice

### Gérant pharmacie
- Dok Pharma (commandes de sa pharmacie)
- Historique pharmacie (commandes de sa pharmacie)
- Validation disponibilité des produits
- Validation du retrait par le livreur
- Création et gestion des vendeurs de sa pharmacie
- Pas d’accès : création de commandes, backoffice global, pharmacies, médicaments, clients

### Vendeur
- Dok Pharma (commandes de sa pharmacie)
- Validation disponibilité des produits
- Validation du retrait par le livreur
- Pas d’accès : création de commandes, historique, création de vendeurs, backoffice

---

## Routes protégées

| Route | Rôles autorisés |
|-------|-----------------|
| `POST /commandes` | admin, super_admin, agent_call_center |
| `GET/POST /agent/*` | admin, super_admin, agent_call_center |
| `GET/POST /dok-pharma/*` | vendeur, gerant |
| `GET/POST/PATCH/DELETE /utilisateurs` | admin, super_admin |
| `GET/POST /pharmacie/vendeurs` | gerant |
