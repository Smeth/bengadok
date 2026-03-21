import { useState } from "react";
import svgPaths from "../../imports/svg-n78j0upy1w";
import imgPhotoDeProfil1 from "figma:asset/5e48039e7a6e82730827415ea7f62a4505d0384f.png";
import imgGeminiGeneratedImageCsgywdcsgywdcsgyRemovebgPreview11 from "figma:asset/74db711eeaa4dd300307a93d2f91d234fe25479c.png";
import imgGeminiGeneratedImageKughr6Kughr6KughRemovebgPreview1 from "figma:asset/0112e96aeef9cd395e0ac5b324f868a6b2ebc070.png";
import imgGeminiGeneratedImageMg6Zgmg6Zgmg6ZgmRemovebgPreview1 from "figma:asset/d4d04f74b1afb302ab56eb97b07c099916e2a363.png";
import imgGeminiGeneratedImageXeeemjxeeemjxeeeRemovebgPreview1 from "figma:asset/bf96c447d81a166d68c26134eb659adeb2a627a5.png";
import imgGeminiGeneratedImageXjtvvixjtvvixjtvRemovebgPreview1 from "figma:asset/4a62353ebd8b7ec036d8e8d5f6bfcb7a7674138f.png";
import imgGeminiGeneratedImage2Zg7Ak2Zg7Ak2Zg7RemovebgPreview1 from "figma:asset/838d00d1474b055a2b733396a77bf3adb3da9229.png";

// Types
export interface DashboardData {
  revenue: {
    value: string;
    change: string;
    period: string;
  };
  pharmacies: {
    value: number;
    subtitle: string;
    change: string;
    period: string;
  };
  orders: {
    value: number;
    unit: string;
    change: string;
    period: string;
  };
  patients: {
    value: number;
    subtitle: string;
    change: string;
    period: string;
  };
  pharmacyOrders?: Array<{ name: string; value: number }>;
  zoneDistribution?: Array<{ name: string; percentage: string; color: string }>;
  userName?: string;
  userRole?: string;
}

const defaultData: DashboardData = {
  revenue: {
    value: "100000",
    change: "+2.5%",
    period: "Depuis cette semaine",
  },
  pharmacies: {
    value: 10,
    subtitle: "Pharmacies de Jour",
    change: "+2.5%",
    period: "Depuis cette semaine",
  },
  orders: {
    value: 300,
    unit: "cmd",
    change: "+2.5%",
    period: "Depuis cette semaine",
  },
  patients: {
    value: 38,
    subtitle: "Depuis cette semaine",
    change: "+2.5%",
    period: "Depuis cette semaine",
  },
  pharmacyOrders: [
    { name: "RenandeetMaat", value: 50 },
    { name: "Vander Veecken", value: 15 },
    { name: "Bet'leem", value: 25 },
    { name: "Clairon", value: 15 },
    { name: "Cristale", value: 20 },
    { name: "Jagger", value: 45 },
  ],
  zoneDistribution: [
    { name: "Moungali", percentage: "38%", color: "#3995D2" },
    { name: "Poto-Poto", percentage: "30%", color: "#DC3545" },
    { name: "Bacongo", percentage: "18%", color: "#FD7E14" },
    { name: "Makélékélé", percentage: "23%", color: "#FFC107" },
    { name: "Ouenzé", percentage: "15%", color: "#198754" },
    { name: "Mfilou", percentage: "15%", color: "#6610F2" },
  ],
  userName: "Merc Mig's",
  userRole: "Admin",
};

export interface DashboardProps {
  data?: DashboardData;
  onNavigate?: (page: string) => void;
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
  // Safely access the SVG path
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
function Sidebar({ userName, userRole, onNavigate }: { userName?: string; userRole?: string; onNavigate?: (page: string) => void }) {
  const [activePage, setActivePage] = useState("dashboard");

  const handleNavigation = (page: string) => {
    setActivePage(page);
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
        <MenuItem icon="p316d1680" label="Tableau de bord" active={activePage === "dashboard"} onClick={() => handleNavigation("dashboard")} />
        <MenuItem icon="p85d1500" label="Commandes" active={activePage === "orders"} onClick={() => handleNavigation("orders")} />
        <MenuItem icon="p35691900" label="Pharmacies" active={activePage === "pharmacies"} onClick={() => handleNavigation("pharmacies")} />
        <MenuItem icon="p20e2ed00" label="Médicaments" active={activePage === "medications"} onClick={() => handleNavigation("medications")} />
        <MenuItem icon="p214df580" label="Clients" active={activePage === "clients"} onClick={() => handleNavigation("clients")} />
        <MenuItem icon="p11af0b00" label="Utilisateurs Backoffice" active={activePage === "users"} onClick={() => handleNavigation("users")} />
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

// Stat Card
function StatCard({ title, value, subtitle, change, period }: { title: string; value: string; subtitle?: string; change: string; period: string }) {
  const svgData = (svgPaths as any).p17d7cb00 || "";
  
  return (
    <div className="bg-white rounded-[20px] shadow-lg p-6 relative overflow-hidden">
      <h3 className="text-[14px] font-bold text-gray-900 mb-2">{title}</h3>
      <div className="flex items-baseline gap-2 mb-1">
        <span className="text-[32px] font-bold text-gray-900">{value}</span>
        {subtitle && <span className="text-[12px] text-gray-600">{subtitle}</span>}
      </div>
      <div className="flex items-center gap-2 text-[10px]">
        <span className="text-green-600 flex items-center gap-1">
          <svg width="12" height="10" viewBox="0 0 12 10" fill="none">
            {svgData && <path d={svgData} fill="#198754" />}
          </svg>
          {change}
        </span>
        <span className="text-gray-500">{period}</span>
      </div>
    </div>
  );
}

// Bar Chart pour pharmacies
function PharmacyBarChart({ data }: { data: Array<{ name: string; value: number }> }) {
  const maxValue = Math.max(...data.map((d) => d.value));
  const dropdownSvg = (svgPaths as any).p1da9a000 || "";

  return (
    <div className="bg-white rounded-[30px] shadow-lg p-6">
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-[16px] font-bold text-gray-900">Volume commandes par pharmacies</h3>
        <button className="border border-black rounded-[13px] px-3 py-1 text-[14px] flex items-center gap-1">
          Ce mois
          <svg width="9" height="5" viewBox="0 0 9 5" fill="none">
            {dropdownSvg && <path d={dropdownSvg} fill="black" />}
          </svg>
        </button>
      </div>

      <div className="relative h-[300px] flex items-end justify-around gap-4 px-4">
        {/* Y-axis labels */}
        <div className="absolute left-0 top-0 bottom-8 flex flex-col justify-between text-[12px] text-gray-600">
          <span>50</span>
          <span>35</span>
          <span>30</span>
          <span>25</span>
          <span>20</span>
          <span>15</span>
          <span>10</span>
          <span>5</span>
        </div>

        {/* Bars */}
        <div className="flex-1 flex items-end justify-around gap-3 ml-8">
          {data.map((item, index) => (
            <div key={index} className="flex flex-col items-center flex-1">
              <div
                className="bg-[#5bb66e] w-full rounded-t-[3px] transition-all hover:bg-[#4ca55d]"
                style={{ height: `${(item.value / maxValue) * 250}px` }}
              />
              <span className="text-[10px] text-gray-700 mt-2 text-center whitespace-nowrap">{item.name}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// Pie Chart pour zones
function ZonePieChart({ data }: { data: Array<{ name: string; percentage: string; color: string }> }) {
  let currentAngle = 0;
  const dropdownSvg = (svgPaths as any).p1da9a000 || "";

  const slices = data.map((item) => {
    const percentage = parseFloat(item.percentage);
    const angle = (percentage / 100) * 360;
    const startAngle = currentAngle;
    currentAngle += angle;
    return { ...item, startAngle, angle, percentage };
  });

  return (
    <div className="bg-white rounded-[30px] shadow-lg p-6">
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-[16px] font-bold text-gray-900">Volume commandes par zone</h3>
        <button className="border border-black rounded-[13px] px-3 py-1 text-[14px] flex items-center gap-1">
          Ce mois
          <svg width="9" height="5" viewBox="0 0 9 5" fill="none">
            {dropdownSvg && <path d={dropdownSvg} fill="black" />}
          </svg>
        </button>
      </div>

      <div className="flex items-center justify-center gap-8">
        {/* Pie chart */}
        <div className="relative size-[250px]">
          <svg viewBox="0 0 200 200" className="size-full -rotate-90">
            {slices.map((slice, index) => {
              const radius = 80;
              const cx = 100;
              const cy = 100;
              const startAngle = (slice.startAngle * Math.PI) / 180;
              const endAngle = ((slice.startAngle + slice.angle) * Math.PI) / 180;

              const x1 = cx + radius * Math.cos(startAngle);
              const y1 = cy + radius * Math.sin(startAngle);
              const x2 = cx + radius * Math.cos(endAngle);
              const y2 = cy + radius * Math.sin(endAngle);

              const largeArcFlag = slice.angle > 180 ? 1 : 0;

              return (
                <path
                  key={index}
                  d={`M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2} Z`}
                  fill={slice.color}
                  className="hover:opacity-80 transition-opacity"
                />
              );
            })}
          </svg>
          
          {/* Center icon */}
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="size-[80px] bg-white rounded-full flex items-center justify-center">
              <img src={imgGeminiGeneratedImageCsgywdcsgywdcsgyRemovebgPreview11} alt="" className="size-[60px] object-contain" />
            </div>
          </div>
        </div>

        {/* Legend */}
        <div className="space-y-3">
          {data.map((item, index) => (
            <div key={index} className="flex items-center gap-3">
              <div className="size-[12px] rounded-sm" style={{ backgroundColor: item.color }} />
              <span className="text-[14px] font-bold" style={{ color: item.color }}>
                {item.name}
              </span>
              <span className="text-[14px] text-gray-600 ml-auto">{item.percentage}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export function Dashboard({ data = defaultData, onNavigate }: DashboardProps = {}) {
  return (
    <div className="bg-white relative size-full flex" data-name="dashboard">
      <BackgroundColor />

      {/* Sidebar */}
      <Sidebar userName={data.userName} userRole={data.userRole} onNavigate={onNavigate} />

      {/* Main content */}
      <main className="flex-1 overflow-y-auto p-8 relative z-10">
        {/* Header avec user */}
        <div className="flex items-center justify-end gap-4 mb-6">
          <button className="size-[40px] bg-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg transition-shadow relative">
            <svg className="size-[20px]" fill="none" viewBox="0 0 24 24">
              <path d={svgPaths.p25e39680} fill="#5C5959" />
            </svg>
            <div className="absolute top-0 right-0 size-[12px] bg-red-500 rounded-full border-2 border-white" />
          </button>
          <div className="flex items-center gap-2 bg-white rounded-full shadow-md px-3 py-2">
            <div className="size-[32px] rounded-full overflow-hidden">
              <img src={imgPhotoDeProfil1} alt={data.userName} className="size-full object-cover" />
            </div>
            <div className="text-right">
              <p className="text-[12px] font-bold text-gray-900">{data.userName}</p>
              <p className="text-[10px] text-gray-600">{data.userRole}</p>
            </div>
            <svg className="size-[16px]" fill="none" viewBox="0 0 17 11">
              <path d={svgPaths.p15992980} fill="#5C5959" />
            </svg>
          </div>
        </div>

        {/* Hero section */}
        <div className="bg-white rounded-[30px] shadow-lg p-8 mb-6 relative overflow-hidden">
          {/* Icônes décoratives */}
          <div className="absolute flex items-center justify-center left-[586px] size-[178.042px] top-[-57px]">
            <div className="flex-none rotate-[3.55deg]">
              <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[167.956px]">
                <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageCsgywdcsgywdcsgyRemovebgPreview11} />
              </div>
            </div>
          </div>
          <div className="absolute flex items-center justify-center left-[947.51px] size-[224.514px] top-px">
            <div className="flex-none rotate-[-17.3deg]">
              <div className="relative shadow-[0px_4px_4px_0px_rgba(0,0,0,0.25)] size-[179.301px]">
                <img alt="" className="absolute inset-0 max-w-none object-cover pointer-events-none size-full" src={imgGeminiGeneratedImageKughr6Kughr6KughRemovebgPreview1} />
              </div>
            </div>
          </div>

          <h1 className="text-[40px] font-bold text-[#5c5959] leading-tight max-w-[450px] relative z-10">
            Toutes les données au même endroit
          </h1>
        </div>

        {/* Stats cards */}
        <div className="grid grid-cols-4 gap-4 mb-6">
          <StatCard
            title="Revenus Total Comission"
            value={data.revenue.value}
            subtitle="XAF"
            change={data.revenue.change}
            period={data.revenue.period}
          />
          <StatCard
            title="Pharmacies"
            value={data.pharmacies.value.toString()}
            subtitle={data.pharmacies.subtitle}
            change={data.pharmacies.change}
            period={data.pharmacies.period}
          />
          <StatCard
            title="Commandes Total"
            value={data.orders.value.toString()}
            subtitle={data.orders.unit}
            change={data.orders.change}
            period={data.orders.period}
          />
          <StatCard
            title="Patientèles"
            value={data.patients.value.toString()}
            subtitle={data.patients.subtitle}
            change={data.patients.change}
            period={data.patients.period}
          />
        </div>

        {/* Charts */}
        <div className="grid grid-cols-2 gap-6">
          {data.pharmacyOrders && <PharmacyBarChart data={data.pharmacyOrders} />}
          {data.zoneDistribution && <ZonePieChart data={data.zoneDistribution} />}
        </div>
      </main>
    </div>
  );
}