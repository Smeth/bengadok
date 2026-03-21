import { Dashboard } from "./components/Dashboard";

export default function App() {
  const handleNavigate = (page: string) => {
    console.log("Navigation vers:", page);
  };

  return (
    <div className="size-full">
      <Dashboard onNavigate={handleNavigate} />
    </div>
  );
}