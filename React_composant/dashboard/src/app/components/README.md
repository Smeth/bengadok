# Dashboard Component - BengaDok

Un composant React complet et réutilisable pour créer un tableau de bord administratif moderne.

## Caractéristiques

✅ **Composant TypeScript** avec typage complet  
✅ **Totalement réutilisable** avec props personnalisables  
✅ **Responsive** et moderne  
✅ **Graphiques interactifs** (Recharts)  
✅ **Navigation fonctionnelle** avec sidebar  
✅ **Données mock incluses** pour démarrage rapide  

## Installation

Le composant utilise les dépendances suivantes (déjà installées) :
- `lucide-react` - Icônes
- `recharts` - Graphiques

## Utilisation de base

```tsx
import { Dashboard } from "./components/Dashboard";

function App() {
  return <Dashboard />;
}
```

## Utilisation avec données personnalisées

```tsx
import { Dashboard } from "./components/Dashboard";

function App() {
  const stats = {
    revenue: 35000000,
    pharmacies: 200,
    orders: 2500,
    patients: 15000,
  };

  const pharmacyOrders = [
    { name: "Pharmacie A", orders: 60 },
    { name: "Pharmacie B", orders: 45 },
    { name: "Pharmacie C", orders: 30 },
  ];

  const zoneDistribution = [
    { name: "Nord", value: 40, color: "#3995D2" },
    { name: "Sud", value: 35, color: "#DC3545" },
    { name: "Est", value: 25, color: "#FD7E14" },
  ];

  const clients = [
    {
      id: "1",
      name: "Alice Dupont",
      gender: "F",
      phone: "+242 06 111 22 33",
      address: "10 avenue principale",
      source: "Publicité",
      type: "Nouveau",
      orders: 8,
      avatar: "https://example.com/avatar.jpg",
    },
  ];

  return (
    <Dashboard
      stats={stats}
      pharmacyOrders={pharmacyOrders}
      zoneDistribution={zoneDistribution}
      clients={clients}
      userName="Admin Principal"
      userAvatar="https://example.com/user.jpg"
    />
  );
}
```

## Props

### DashboardProps

| Prop | Type | Défaut | Description |
|------|------|--------|-------------|
| `stats` | `DashboardStats` | Données par défaut | Statistiques principales |
| `pharmacyOrders` | `PharmacyOrder[]` | Données par défaut | Volume de commandes par pharmacie |
| `zoneDistribution` | `ZoneDistribution[]` | Données par défaut | Répartition géographique |
| `clients` | `Client[]` | Données par défaut | Liste des clients |
| `userName` | `string` | "Admin BengaDok" | Nom de l'utilisateur |
| `userAvatar` | `string` | `undefined` | URL de l'avatar utilisateur |

### Types détaillés

```typescript
interface DashboardStats {
  revenue: number;      // Revenus totaux
  pharmacies: number;   // Nombre de pharmacies
  orders: number;       // Nombre de commandes
  patients: number;     // Nombre de patients
}

interface PharmacyOrder {
  name: string;         // Nom de la pharmacie
  orders: number;       // Nombre de commandes
}

interface ZoneDistribution {
  name: string;         // Nom de la zone
  value: number;        // Valeur/pourcentage
  color: string;        // Couleur (hex)
}

interface Client {
  id: string;           // ID unique
  name: string;         // Nom du client
  gender: string;       // Genre (M/F)
  phone: string;        // Numéro de téléphone
  address: string;      // Adresse
  source: string;       // Source d'acquisition
  type: string;         // Type de client
  orders: number;       // Nombre de commandes
  avatar: string;       // URL de l'avatar
}
```

## Fonctionnalités

### 1. Sidebar de navigation
- Navigation entre les pages
- Indicateur visuel de la page active
- Profil utilisateur
- Bouton de déconnexion

### 2. Statistiques clés
- Revenus totaux
- Nombre de pharmacies
- Nombre de commandes
- Nombre de patients

### 3. Graphiques
- **Graphique à barres** : Volume de commandes par pharmacie
- **Graphique circulaire** : Répartition par zone géographique

### 4. Table des clients
- Liste complète des clients
- Informations détaillées
- Badges colorés pour sources et types
- Avatars personnalisables

## Personnalisation

### Couleurs des zones
Vous pouvez personnaliser les couleurs des zones géographiques :

```tsx
const customZones = [
  { name: "Zone A", value: 40, color: "#FF6B6B" },
  { name: "Zone B", value: 30, color: "#4ECDC4" },
  { name: "Zone C", value: 30, color: "#45B7D1" },
];
```

### Callback de navigation
Le composant gère la navigation en interne. Pour connecter à un router :

```tsx
// Modifiez le composant Sidebar pour accepter un callback
const handleNavigate = (page: string) => {
  console.log(`Navigation vers: ${page}`);
  // Ajoutez votre logique de routing ici
};
```

## Exemples de données

Le composant inclut des données d'exemple réalistes pour :
- 6 pharmacies avec volume de commandes
- 6 zones géographiques avec distribution
- 5 clients avec informations complètes

## Responsive Design

Le dashboard s'adapte automatiquement aux différentes tailles d'écran :
- **Mobile** : Colonnes empilées
- **Tablet** : 2 colonnes
- **Desktop** : 4 colonnes pour les stats, 2 pour les graphiques

## Notes

- Toutes les props sont optionnelles - le composant fonctionne avec les données par défaut
- Les images utilisent Unsplash pour les avatars par défaut
- Le composant est entièrement typé avec TypeScript
- Prêt pour l'intégration avec des APIs backend
