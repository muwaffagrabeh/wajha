"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import {
  getBusinesses,
  getServices,
  getStats,
  createService,
  updateService,
  toggleService,
} from "@/lib/api";

interface Service {
  id: string;
  name: string;
  price: string;
  type: string;
  category: string | null;
  duration_minutes: number | null;
  status: string;
}

interface Branch {
  id: string;
  name: string;
  city: string;
  district: string | null;
  working_hours: { from: string; to: string } | null;
}

interface Business {
  id: string;
  name: string;
  sector: string;
  sector_type: string;
  branches: Branch[];
}

interface Stats {
  conversations: { total: number; resolved: number; escalated: number; auto_resolve_rate: string };
  messages: { total: number; gateway_handled: number; gateway_rate: string };
  quality: { errors_caught: number };
  risk_score: number;
}

export default function DashboardPage() {
  const router = useRouter();
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [activeBiz, setActiveBiz] = useState<Business | null>(null);
  const [services, setServices] = useState<Service[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);

  // Add service modal
  const [showAdd, setShowAdd] = useState(false);
  const [newName, setNewName] = useState("");
  const [newPrice, setNewPrice] = useState("");
  const [newType, setNewType] = useState("service");

  // Edit service
  const [editId, setEditId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");
  const [editPrice, setEditPrice] = useState("");

  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) { router.push("/login"); return; }
    loadData();
  }, []);

  async function loadData() {
    try {
      const biz = await getBusinesses();
      setBusinesses(biz);
      if (biz.length > 0) {
        setActiveBiz(biz[0]);
        const [svc, st] = await Promise.all([
          getServices(biz[0].id),
          getStats(biz[0].id),
        ]);
        setServices(svc);
        setStats(st);
      }
    } catch {
      router.push("/login");
    }
    setLoading(false);
  }

  async function handleAddService() {
    if (!activeBiz || !newName || !newPrice) return;
    await createService(activeBiz.id, { name: newName, price: parseFloat(newPrice), type: newType });
    setNewName(""); setNewPrice(""); setShowAdd(false);
    setServices(await getServices(activeBiz.id));
  }

  async function handleUpdate() {
    if (!activeBiz || !editId) return;
    await updateService(activeBiz.id, editId, {
      name: editName || undefined,
      price: editPrice ? parseFloat(editPrice) : undefined,
    });
    setEditId(null);
    setServices(await getServices(activeBiz.id));
  }

  async function handleToggle(svc: Service) {
    if (!activeBiz) return;
    await toggleService(activeBiz.id, svc.id, svc.status !== "active");
    setServices(await getServices(activeBiz.id));
  }

  function logout() {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    router.push("/login");
  }

  if (loading) return <div className="flex min-h-screen items-center justify-center"><p className="text-gray-400">جاري التحميل...</p></div>;

  return (
    <div className="min-h-screen p-4 md:p-8 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">لوحة التحكم</h1>
        <div className="flex gap-3">
          <Link href="/chat/ali" className="rounded-lg bg-blue-600 px-4 py-2 text-sm hover:bg-blue-700 transition">
            محادثة علي
          </Link>
          <button onClick={logout} className="rounded-lg bg-gray-800 px-4 py-2 text-sm hover:bg-gray-700 transition">
            خروج
          </button>
        </div>
      </div>

      {/* Business Info */}
      {activeBiz && (
        <div className="rounded-xl bg-gray-900 p-5">
          <h2 className="text-xl font-bold">{activeBiz.name}</h2>
          <p className="text-gray-400 text-sm mt-1">{activeBiz.sector} &gt; {activeBiz.sector_type}</p>
          <div className="mt-3 flex flex-wrap gap-3">
            {activeBiz.branches.map((b) => (
              <span key={b.id} className="rounded-lg bg-gray-800 px-3 py-1.5 text-sm">
                {b.name} — {b.city} {b.working_hours ? `(${b.working_hours.from}-${b.working_hours.to})` : ""}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { label: "محادثات", value: stats.conversations.total, color: "blue" },
            { label: "حُلّت تلقائياً", value: stats.conversations.auto_resolve_rate, color: "green" },
            { label: "تصعيد", value: stats.conversations.escalated, color: "yellow" },
            { label: "أخطاء مُنعت", value: stats.quality.errors_caught, color: "red" },
          ].map((s) => (
            <div key={s.label} className="rounded-xl bg-gray-900 p-4 text-center">
              <p className="text-2xl font-bold">{s.value}</p>
              <p className="text-sm text-gray-400 mt-1">{s.label}</p>
            </div>
          ))}
        </div>
      )}

      {/* Services */}
      <div className="rounded-xl bg-gray-900 p-5">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold">الخدمات والمنتجات</h3>
          <button onClick={() => setShowAdd(!showAdd)} className="rounded-lg bg-blue-600 px-3 py-1.5 text-sm hover:bg-blue-700 transition">
            + إضافة
          </button>
        </div>

        {/* Add Form */}
        {showAdd && (
          <div className="mb-4 flex flex-wrap gap-2">
            <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="الاسم" className="rounded-lg bg-gray-800 px-3 py-2 text-sm flex-1 min-w-[120px] focus:outline-none focus:ring-1 focus:ring-blue-500" />
            <input value={newPrice} onChange={(e) => setNewPrice(e.target.value)} placeholder="السعر" type="number" className="rounded-lg bg-gray-800 px-3 py-2 text-sm w-24 focus:outline-none focus:ring-1 focus:ring-blue-500" dir="ltr" />
            <select value={newType} onChange={(e) => setNewType(e.target.value)} className="rounded-lg bg-gray-800 px-3 py-2 text-sm focus:outline-none">
              <option value="service">خدمة</option>
              <option value="product">منتج</option>
            </select>
            <button onClick={handleAddService} className="rounded-lg bg-green-600 px-4 py-2 text-sm hover:bg-green-700 transition">حفظ</button>
          </div>
        )}

        {/* Services Table */}
        <div className="space-y-2">
          {services.map((svc) => (
            <div key={svc.id} className={`flex items-center justify-between rounded-lg p-3 ${svc.status === "active" ? "bg-gray-800" : "bg-gray-800/50 opacity-60"}`}>
              {editId === svc.id ? (
                <div className="flex flex-wrap gap-2 flex-1">
                  <input value={editName} onChange={(e) => setEditName(e.target.value)} className="rounded bg-gray-700 px-2 py-1 text-sm flex-1 min-w-[100px] focus:outline-none" />
                  <input value={editPrice} onChange={(e) => setEditPrice(e.target.value)} type="number" className="rounded bg-gray-700 px-2 py-1 text-sm w-20 focus:outline-none" dir="ltr" />
                  <button onClick={handleUpdate} className="rounded bg-green-600 px-3 py-1 text-sm">حفظ</button>
                  <button onClick={() => setEditId(null)} className="rounded bg-gray-600 px-3 py-1 text-sm">إلغاء</button>
                </div>
              ) : (
                <>
                  <div>
                    <span className="font-medium">{svc.name}</span>
                    {svc.category && <span className="text-xs text-gray-500 mr-2">({svc.category})</span>}
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-gray-400">{svc.price} ريال</span>
                    <button
                      onClick={() => { setEditId(svc.id); setEditName(svc.name); setEditPrice(svc.price); }}
                      className="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600"
                    >
                      تعديل
                    </button>
                    <button
                      onClick={() => handleToggle(svc)}
                      className={`rounded px-2 py-1 text-xs ${svc.status === "active" ? "bg-red-900 hover:bg-red-800" : "bg-green-900 hover:bg-green-800"}`}
                    >
                      {svc.status === "active" ? "إيقاف" : "تفعيل"}
                    </button>
                  </div>
                </>
              )}
            </div>
          ))}
          {services.length === 0 && (
            <p className="text-center text-gray-500 py-4">لا توجد خدمات بعد</p>
          )}
        </div>
      </div>
    </div>
  );
}
