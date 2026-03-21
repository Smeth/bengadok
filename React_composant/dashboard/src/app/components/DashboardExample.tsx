import { Dashboard } from "./Dashboard";

/**
 * Exemple d'utilisation du composant Dashboard
 * 
 * Ce fichier montre comment utiliser le Dashboard avec des données personnalisées
 */
export function DashboardExample() {
  // Exemple 1: Utilisation avec les données par défaut
  return <Dashboard />;

  // Exemple 2: Utilisation avec des données personnalisées
  /*
  const customStats = {
    revenue: 35000000,
    pharmacies: 200,
    orders: 2500,
    patients: 15000,
  };

  const customPharmacyOrders = [
    { name: "Pharmacie A", orders: 60 },
    { name: "Pharmacie B", orders: 45 },
    { name: "Pharmacie C", orders: 30 },
  ];

  const customZoneDistribution = [
    { name: "Nord", value: 40, color: "#3995D2" },
    { name: "Sud", value: 35, color: "#DC3545" },
    { name: "Est", value: 25, color: "#FD7E14" },
  ];

  const customClients = [
    {
      id: "1",
      name: "Alice Dupont",
      gender: "F",
      phone: "+242 06 111 22 33",
      address: "10 avenue principale",
      source: "Publicité",
      type: "Nouveau",
      orders: 8,
      avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop",
    },
  ];

  return (
    <Dashboard
      stats={customStats}
      pharmacyOrders={customPharmacyOrders}
      zoneDistribution={customZoneDistribution}
      clients={customClients}
      userName="Administrateur Principal"
      userAvatar="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop"
    />
  );
  */
}
