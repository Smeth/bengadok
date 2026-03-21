import { useState } from "react";
import { Dashboard } from "./components/Dashboard";
import { Orders } from "./components/Orders";

export default function App() {
  const [currentPage, setCurrentPage] = useState<string>("dashboard");

  const handleNavigate = (page: string) => {
    console.log("Navigation vers:", page);
    setCurrentPage(page);
  };

  const handleOrderAction = (orderId: string, action: "view" | "more") => {
    console.log(`Action ${action} sur commande:`, orderId);
  };

  return (
    <div className="size-full">
      {currentPage === "dashboard" && <Dashboard onNavigate={handleNavigate} />}
      {currentPage === "orders" && <Orders onNavigate={handleNavigate} onOrderAction={handleOrderAction} />}
    </div>
  );
}