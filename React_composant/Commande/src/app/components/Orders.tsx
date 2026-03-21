import { useState } from "react";
import svgPaths from "../../imports/svg-r9gnqsixyi";
import imgGeminiGeneratedImageKughr6Kughr6KughRemovebgPreview1 from "figma:asset/0112e96aeef9cd395e0ac5b324f868a6b2ebc070.png";
import imgGeminiGeneratedImageMg6Zgmg6Zgmg6ZgmRemovebgPreview1 from "figma:asset/d4d04f74b1afb302ab56eb97b07c099916e2a363.png";
import imgGeminiGeneratedImageXeeemjxeeemjxeeeRemovebgPreview1 from "figma:asset/bf96c447d81a166d68c26134eb659adeb2a627a5.png";
import imgGeminiGeneratedImageXjtvvixjtvvixjtvRemovebgPreview1 from "figma:asset/4a62353ebd8b7ec036d8e8d5f6bfcb7a7674138f.png";
import imgPhotoDeProfil1 from "figma:asset/5e48039e7a6e82730827415ea7f62a4505d0384f.png";
import imgGeminiGeneratedImage2Zg7Ak2Zg7Ak2Zg7RemovebgPreview1 from "figma:asset/838d00d1474b055a2b733396a77bf3adb3da9229.png";
import imgGeminiGeneratedImageKvtgykvtgykvtgyk1 from "figma:asset/d6859d2d3c4f447806b2e2a84194a4441f1655bc.png";

// Types
export type OrderStatus = "Nouvelle Commande" | "En Attente" | "Annulée" | "Validée" | "Livrée";

export interface Order {
  id: string;
  client: string;
  sex: "M" | "F";
  phone: string;
  date: string;
  address: string;
  medication: string;
  amount: number;
  status: OrderStatus;
}

export interface OrdersData {
  orders: Order[];
  userName?: string;
  userRole?: string;
}

const defaultOrders: Order[] = [
  {
    id: "BDK3006001",
    client: "Amélia",
    sex: "F",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Temesta 2,5mg",
    amount: 2800,
    status: "Validée",
  },
  {
    id: "BDK3006001",
    client: "Ludovic",
    sex: "M",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Artefan Sirop Nourisson",
    amount: 1425,
    status: "En Attente",
  },
  {
    id: "BDK3006001",
    client: "Jenifer",
    sex: "F",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Antalgex",
    amount: 5450,
    status: "En Attente",
  },
  {
    id: "BDK3006001",
    client: "Jamila",
    sex: "F",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Cipro denk 500mg",
    amount: 2100,
    status: "Annulée",
  },
  {
    id: "BDK3006001",
    client: "Jean-Pierre",
    sex: "M",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Confantrine Adulte",
    amount: 4300,
    status: "Livrée",
  },
  {
    id: "BDK3006001",
    client: "Fatou",
    sex: "F",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Daflom 500mg CP",
    amount: 1765,
    status: "Livrée",
  },
  {
    id: "BDK3006001",
    client: "Fatou",
    sex: "F",
    phone: "+242 06 536 90 86",
    date: "12/11/2026",
    address: "16 rue Djouari moukounziguaka",
    medication: "Daflom 500mg CP",
    amount: 1765,
    status: "Livrée",
  },
];

export interface OrdersProps {
  data?: OrdersData;
  onNavigate?: (page: string) => void;
  onOrderAction?: (orderId: string, action: "view" | "more") => void;
}

function BackgroundColor() {
  return (
    <div
      className="absolute blur-[50px] h-[1140px] left-0 opacity-82 top-0 w-[1800px]"
      data-name="background_color"
      style={{
        backgroundImage:
          "linear-gradient(60.0211deg, rgb(57, 149, 210) 35.89%, rgb(91, 182, 110) 92.848%)",
      }}
    />
  );
}

// Logo BengaDok
function LogoBengaDok() {
  return (
    <div className="flex items-center gap-2">
      <div className="size-[40px] bg-white rounded-full flex items-center justify-center">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="14" fill="#3995D2" />
          <path
            d="M16 8C12.7 8 10 10.7 10 14C10 17.3 12.7 20 16 20C19.3 20 22 17.3 22 14C22 10.7 19.3 8 16 8ZM16 18C13.8 18 12 16.2 12 14C12 11.8 13.8 10 16 10C18.2 10 20 11.8 20 14C20 16.2 18.2 18 16 18Z"
            fill="white"
          />
          <path d="M16 12C14.9 12 14 12.9 14 14C14 15.1 14.9 16 16 16C17.1 16 18 15.1 18 14C18 12.9 17.1 12 16 12Z" fill="white" />
        </svg>
      </div>
      <div>
        <span className="text-[#3995D2] font-bold text-[18px]">Benga</span>
        <span className="text-[#5BB66E] font-bold text-[18px]">Dok</span>
      </div>
    </div>
  );
}

// Menu items
function MenuItem({ icon, label, active, onClick }: { icon: string; label: string; active?: boolean; onClick?: () => void }) {
  const pathData = (svgPaths as any)[icon] || "";
  
  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-3 w-full px-3 py-2 rounded-lg transition-all ${
        active ? "bg-gray-900 text-white" : "text-gray-700 hover:bg-gray-100"
      }`}
    >
      <div className={`size-[36px] rounded-full flex items-center justify-center ${active ? "bg-white/20" : "bg-[rgba(92,89,89,0.25)]"}`}>
        <svg className="size-[24px]" fill="none" viewBox="0 0 24 24">
          {pathData && <path d={pathData} fill={active ? "white" : "#5C5959"} />}
        </svg>
      </div>
      <span className="text-[14px] font-medium">{label}</span>
    </button>
  );
}

// Sidebar
function Sidebar({ userName, userRole, activePage = "orders", onNavigate }: { userName?: string; userRole?: string; activePage?: string; onNavigate?: (page: string) => void }) {
  const [currentPage, setCurrentPage] = useState(activePage);

  const handleNavigation = (page: string) => {
    setCurrentPage(page);
    onNavigate?.(page);
  };

  return (
    <div className="bg-white h-screen w-[200px] shadow-lg flex flex-col relative overflow-hidden">
      {/* Images décoratives en bas */}
      <div className="absolute flex items-center justify-center left-[-65px] size-[224.514px] top-[653px]">
        <div className="flex-none rotate-[-17.3deg]">
          <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[179.301px]">
            <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageKughr6Kughr6KughRemovebgPreview1} />
          </div>
        </div>
      </div>
      <div className="absolute flex items-center justify-center left-[175.85px] size-[139.423px] top-[663.48px]">
        <div className="flex-none rotate-[9.83deg]">
          <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[120.603px]">
            <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageMg6Zgmg6Zgmg6ZgmRemovebgPreview1} />
          </div>
        </div>
      </div>
      <div className="absolute flex items-center justify-center left-[67px] size-[199.398px] top-[663px]">
        <div className="flex-none rotate-[26.65deg]">
          <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[148.546px]">
            <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageXeeemjxeeemjxeeeRemovebgPreview1} />
          </div>
        </div>
      </div>
      <div className="absolute flex items-center justify-center left-[188px] size-[200.961px] top-[703px]">
        <div className="flex-none rotate-[12.62deg]">
          <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[168.256px]">
            <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageXjtvvixjtvvixjtvRemovebgPreview1} />
          </div>
        </div>
      </div>

      {/* Logo */}
      <div className="p-4 border-b border-gray-200 relative z-10">
        <LogoBengaDok />
      </div>

      {/* Menu items */}
      <nav className="flex-1 p-3 space-y-2 relative z-10">
        <MenuItem icon="p316d1680" label="Tableau de bord" active={currentPage === "dashboard"} onClick={() => handleNavigation("dashboard")} />
        <MenuItem icon="p85d1500" label="Commandes" active={currentPage === "orders"} onClick={() => handleNavigation("orders")} />
        <MenuItem icon="p35691900" label="Pharmacies" active={currentPage === "pharmacies"} onClick={() => handleNavigation("pharmacies")} />
        <MenuItem icon="p20e2ed00" label="Médicaments" active={currentPage === "medications"} onClick={() => handleNavigation("medications")} />
        <MenuItem icon="p214df580" label="Clients" active={currentPage === "clients"} onClick={() => handleNavigation("clients")} />
        <MenuItem icon="p11af0b00" label="Utilisateurs Backoffice" active={currentPage === "users"} onClick={() => handleNavigation("users")} />
      </nav>

      {/* Réglages et Déconnexion */}
      <div className="p-3 border-t border-gray-200 space-y-2 relative z-10">
        <MenuItem icon="pab73400" label="Réglages" onClick={() => handleNavigation("settings")} />
        
        {/* Illustration de déconnexion */}
        <div className="relative h-[200px] -mx-3 -mb-3">
          <div className="absolute h-[337.066px] left-[10px] top-[20px] w-[180px]">
            <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImage2Zg7Ak2Zg7Ak2Zg7RemovebgPreview1} />
          </div>
          <button className="absolute bottom-4 left-1/2 -translate-x-1/2 bg-[#5c5959] text-white px-4 py-2 rounded-full text-[12px] font-medium hover:bg-[#4a4747] transition-colors flex items-center gap-2 z-10">
            <svg className="size-[18px]" fill="none" viewBox="0 0 24 24">
              <path d={(svgPaths as any).p3089d000 || ""} fill="white" />
            </svg>
            Déconnexion
          </button>
        </div>
      </div>
    </div>
  );
}

// Status Badge
function StatusBadge({ status }: { status: OrderStatus }) {
  const statusConfig = {
    "Nouvelle Commande": { bg: "#0d6efd", text: "white", border: "none" },
    "En Attente": { bg: "#ffc107", text: "white", border: "none" },
    "Annulée": { bg: "#e7000b", text: "white", border: "none" },
    "Validée": { bg: "#198754", text: "white", border: "none" },
    "Livrée": { bg: "white", text: "#016630", border: "#016630" },
  };

  const config = statusConfig[status];

  return (
    <div
      className="rounded-[10px] px-4 py-1.5 text-[14px] font-bold whitespace-nowrap"
      style={{
        backgroundColor: config.bg,
        color: config.text,
        border: config.border !== "none" ? `1px solid ${config.border}` : "none",
      }}
    >
      {status}
    </div>
  );
}

// Table Row
function OrderRow({ order, onAction }: { order: Order; onAction?: (orderId: string, action: "view" | "more") => void }) {
  return (
    <div className="border-b border-[rgba(102,102,102,0.42)] py-4">
      <div className="flex items-center gap-4 text-[rgba(0,0,0,0.74)]">
        {/* ID Cmd */}
        <div className="w-[120px] text-[14px] font-normal">{order.id}</div>
        
        {/* Client */}
        <div className="w-[100px] text-[14px] font-normal">{order.client}</div>
        
        {/* Sexe */}
        <div className="w-[40px] text-[18px] font-normal">{order.sex}</div>
        
        {/* Tel */}
        <div className="w-[140px] text-[12px]">
          <span className="font-['JetBrains_Mono'] font-normal">{order.phone.split(" ")[0]}</span>
          <span> {order.phone.split(" ").slice(1).join(" ")}</span>
        </div>
        
        {/* Date */}
        <div className="w-[100px] text-[10px] font-['JetBrains_Mono'] font-normal">{order.date}</div>
        
        {/* Adresse */}
        <div className="w-[200px] text-[10px] font-normal">{order.address}</div>
        
        {/* Médicament */}
        <div className="w-[150px] text-[10px] font-normal">{order.medication}</div>
        
        {/* Montant */}
        <div className="w-[110px] text-[15px] font-['JetBrains_Mono'] font-bold">{order.amount} FCFA</div>
        
        {/* Statut */}
        <div className="w-[130px]">
          <StatusBadge status={order.status} />
        </div>
        
        {/* Actions */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => onAction?.(order.id, "view")}
            className="size-[20px] flex items-center justify-center hover:opacity-70 transition-opacity"
          >
            <svg className="size-full" fill="none" viewBox="0 0 20.16 20.16">
              <path d={(svgPaths as any).p18be300 || ""} fill="#5C5959" />
            </svg>
          </button>
          <button
            onClick={() => onAction?.(order.id, "more")}
            className="size-[20px] flex items-center justify-center hover:opacity-70 transition-opacity"
          >
            <svg className="size-full" fill="none" viewBox="0 0 20.16 20.16">
              <path d={(svgPaths as any).p36f7c700 || ""} fill="#5C5959" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  );
}

// Tabs
function Tabs({ activeTab, onTabChange }: { activeTab: string; onTabChange: (tab: string) => void }) {
  return (
    <div className="flex gap-8 border-b-2 border-transparent mb-6">
      <button
        onClick={() => onTabChange("gestion")}
        className={`pb-3 text-[16px] font-bold transition-colors relative ${
          activeTab === "gestion" ? "text-[#0d6efd]" : "text-gray-600"
        }`}
      >
        Gestion commandes
        {activeTab === "gestion" && (
          <div className="absolute bottom-0 left-0 right-0 h-[3px] bg-[#0d6efd]" />
        )}
      </button>
      <button
        onClick={() => onTabChange("statistiques")}
        className={`pb-3 text-[16px] font-bold transition-colors relative ${
          activeTab === "statistiques" ? "text-[#0d6efd]" : "text-gray-600"
        }`}
      >
        Statistiques
        {activeTab === "statistiques" && (
          <div className="absolute bottom-0 left-0 right-0 h-[3px] bg-[#0d6efd]" />
        )}
      </button>
    </div>
  );
}

// Status Filter
function StatusFilter({ activeStatus, onStatusChange }: { activeStatus: OrderStatus | "Toutes"; onStatusChange: (status: OrderStatus | "Toutes") => void }) {
  const statuses: Array<{ label: OrderStatus | "Toutes"; color: string; textColor: string }> = [
    { label: "Nouvelle Commande", color: "#0d6efd", textColor: "white" },
    { label: "En Attente", color: "#ffc107", textColor: "white" },
    { label: "Annulée", color: "#e7000b", textColor: "white" },
    { label: "Validée", color: "#198754", textColor: "white" },
    { label: "Livrée", color: "white", textColor: "#016630" },
  ];

  return (
    <div className="flex items-center gap-3 flex-wrap mb-6">
      <span className="text-[16px] font-bold text-gray-900">Statut :</span>
      {statuses.map((status) => (
        <button
          key={status.label}
          onClick={() => onStatusChange(status.label)}
          className="rounded-[10px] px-4 py-1.5 text-[14px] font-bold transition-all"
          style={{
            backgroundColor: activeStatus === status.label ? status.color : "transparent",
            color: activeStatus === status.label ? status.textColor : status.color,
            border: activeStatus === status.label ? "none" : `1px solid ${status.color}`,
          }}
        >
          {status.label}
        </button>
      ))}
    </div>
  );
}

// Pagination
function Pagination({ currentPage, totalPages, onPageChange }: { currentPage: number; totalPages: number; onPageChange: (page: number) => void }) {
  return (
    <div className="flex items-center justify-center gap-2 mt-6">
      <button
        onClick={() => onPageChange(Math.max(1, currentPage - 1))}
        disabled={currentPage === 1}
        className="size-[31px] flex items-center justify-center rounded-[10px] border border-[rgba(102,102,102,0.42)] bg-white disabled:opacity-50 hover:bg-gray-50 transition-colors"
      >
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
          <path d={(svgPaths as any).p25b5b900 || ""} fill="#666666" />
        </svg>
      </button>

      {[...Array(Math.min(totalPages, 3))].map((_, i) => {
        const pageNum = i + 1;
        return (
          <button
            key={pageNum}
            onClick={() => onPageChange(pageNum)}
            className={`size-[31px] flex items-center justify-center rounded-[10px] text-[18px] font-bold transition-colors ${
              currentPage === pageNum
                ? "bg-[#3995d2] text-white"
                : "bg-white border border-[rgba(102,102,102,0.42)] text-[rgba(102,102,102,0.42)] hover:bg-gray-50"
            }`}
          >
            {pageNum}
          </button>
        );
      })}

      {totalPages > 3 && (
        <>
          <span className="text-[18px] text-[rgba(102,102,102,0.42)]">...</span>
          <button
            onClick={() => onPageChange(totalPages)}
            className={`size-[31px] flex items-center justify-center rounded-[10px] text-[18px] font-bold transition-colors ${
              currentPage === totalPages
                ? "bg-[#3995d2] text-white"
                : "bg-white border border-[rgba(102,102,102,0.42)] text-[rgba(102,102,102,0.42)] hover:bg-gray-50"
            }`}
          >
            {totalPages}
          </button>
        </>
      )}

      <button
        onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
        disabled={currentPage === totalPages}
        className="size-[31px] flex items-center justify-center rounded-[10px] border border-[rgba(102,102,102,0.42)] bg-white disabled:opacity-50 hover:bg-gray-50 transition-colors"
      >
        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
          <path d={(svgPaths as any).p11a80500 || ""} fill="#666666" />
        </svg>
      </button>

      <span className="ml-4 text-[14px] text-gray-600">Page suivante</span>
    </div>
  );
}

export function Orders({ data, onNavigate, onOrderAction }: OrdersProps = {}) {
  const [activeTab, setActiveTab] = useState("gestion");
  const [searchQuery, setSearchQuery] = useState("");
  const [filterPeriod, setFilterPeriod] = useState("Periode");
  const [filterDate, setFilterDate] = useState("");
  const [activeStatus, setActiveStatus] = useState<OrderStatus | "Toutes">("Toutes");
  const [currentPage, setCurrentPage] = useState(1);
  
  const orders = data?.orders || defaultOrders;
  const userName = data?.userName || "Merc Mig's";
  const userRole = data?.userRole || "Admin";

  // Filter orders
  const filteredOrders = orders.filter((order) => {
    const matchesStatus = activeStatus === "Toutes" || order.status === activeStatus;
    const matchesSearch = searchQuery === "" || 
      order.client.toLowerCase().includes(searchQuery.toLowerCase()) ||
      order.id.toLowerCase().includes(searchQuery.toLowerCase()) ||
      order.medication.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesStatus && matchesSearch;
  });

  const itemsPerPage = 7;
  const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
  const displayedOrders = filteredOrders.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  return (
    <div className="bg-white relative size-full flex" data-name="orders">
      <BackgroundColor />

      {/* Sidebar */}
      <Sidebar userName={userName} userRole={userRole} activePage="orders" onNavigate={onNavigate} />

      {/* Main content */}
      <main className="flex-1 overflow-y-auto p-8 relative z-10">
        {/* Header avec user */}
        <div className="flex items-center justify-end gap-4 mb-6">
          <button className="size-[40px] bg-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg transition-shadow relative">
            <svg className="size-[20px]" fill="none" viewBox="0 0 24 24">
              <path d={(svgPaths as any).p25e39680} fill="#5C5959" />
            </svg>
            <div className="absolute top-0 right-0 size-[12px] bg-red-500 rounded-full border-2 border-white" />
          </button>
          <div className="flex items-center gap-2 bg-white rounded-full shadow-md px-3 py-2">
            <div className="size-[32px] rounded-full overflow-hidden">
              <img src={imgPhotoDeProfil1} alt={userName} className="size-full object-cover" />
            </div>
            <div className="text-right">
              <p className="text-[12px] font-bold text-gray-900">{userName}</p>
              <p className="text-[10px] text-gray-600">{userRole}</p>
            </div>
            <svg className="size-[16px]" fill="none" viewBox="0 0 17 11">
              <path d={(svgPaths as any).p15992980} fill="#5C5959" />
            </svg>
          </div>
        </div>

        {/* Content card */}
        <div className="bg-white rounded-[30px] shadow-lg p-8 relative overflow-hidden">
          {/* Illustration décorative */}
          <div className="absolute left-[-80px] bottom-[-80px] size-[300px] opacity-50 pointer-events-none">
            <img src={imgGeminiGeneratedImageKvtgykvtgykvtgyk1} alt="" className="size-full object-contain" />
          </div>

          <div className="relative z-10">
            {/* Tabs */}
            <Tabs activeTab={activeTab} onTabChange={setActiveTab} />

            {/* Search bar and filters */}
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-4">
                {/* Search */}
                <div className="relative">
                  <svg className="absolute left-3 top-1/2 -translate-y-1/2 size-[18px]" fill="none" viewBox="0 0 24 24">
                    <path d={(svgPaths as any).pc423380} fill="#5C5959" />
                  </svg>
                  <input
                    type="text"
                    placeholder="Recherche commandes (médicaments, téléphone, Noms, ...)"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10 pr-4 py-2 rounded-[10px] border border-gray-300 text-[14px] w-[400px] focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                  />
                </div>

                {/* Filter button */}
                <button className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-[10px] text-[14px] hover:bg-gray-50 transition-colors">
                  <svg className="size-[18px]" fill="none" viewBox="0 0 24 24">
                    <path d={(svgPaths as any).p1f306b80} fill="#5C5959" />
                  </svg>
                  Trier par
                </button>

                {/* Period dropdown */}
                <select
                  value={filterPeriod}
                  onChange={(e) => setFilterPeriod(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-[10px] text-[14px] hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                >
                  <option>Periode</option>
                  <option>Aujourd'hui</option>
                  <option>Cette semaine</option>
                  <option>Ce mois</option>
                </select>
              </div>

              <div className="flex items-center gap-4">
                {/* Date input */}
                <input
                  type="text"
                  placeholder="Date MM:JJ:AAAA"
                  value={filterDate}
                  onChange={(e) => setFilterDate(e.target.value)}
                  className="px-4 py-2 border border-gray-300 rounded-[10px] text-[14px] w-[180px] focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                />

                {/* New order button */}
                <button className="bg-[#0d6efd] text-white px-6 py-2 rounded-[11px] text-[16px] font-bold hover:bg-[#0b5ed7] transition-colors flex items-center gap-2">
                  <svg className="size-[20px]" fill="none" viewBox="0 0 27 27">
                    <path d={(svgPaths as any).p2fd86280} fill="white" />
                  </svg>
                  Nouvelles Commandes
                </button>
              </div>
            </div>

            {/* Status filter */}
            <StatusFilter activeStatus={activeStatus} onStatusChange={setActiveStatus} />

            {/* Table header */}
            <div className="flex items-center gap-4 text-[14px] font-bold text-gray-900 mb-4 pb-3 border-b-2 border-gray-200">
              <div className="w-[120px]">ID Cmd</div>
              <div className="w-[100px]">Client</div>
              <div className="w-[40px]">Sexe</div>
              <div className="w-[140px]">Tel</div>
              <div className="w-[100px]">Date</div>
              <div className="w-[200px]">Adresse</div>
              <div className="w-[150px]">Médicament</div>
              <div className="w-[110px]">Montant</div>
              <div className="w-[130px]">Statut</div>
              <div>Actions</div>
            </div>

            {/* Table rows */}
            <div className="min-h-[400px]">
              {displayedOrders.map((order, index) => (
                <OrderRow key={`${order.id}-${index}`} order={order} onAction={onOrderAction} />
              ))}
            </div>

            {/* Pagination */}
            <Pagination currentPage={currentPage} totalPages={totalPages} onPageChange={setCurrentPage} />
          </div>
        </div>
      </main>
    </div>
  );
}
