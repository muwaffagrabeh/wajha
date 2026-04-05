"use client";

import { useEffect, useRef, useState } from "react";

export default function TryPage() {
  const [messages, setMessages] = useState([
    { role: "ali" as const, text: "أهلاً! أنا علي، مديرك الرقمي.\nقولي وش نوع نشاطك وأجهّز لك كل شي.\n\nمثال: عندي مطعم شاورما اسمه أبو سعد بالمدينة" },
  ]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [bizId, setBizId] = useState("");
  const [token] = useState(() => {
    if (typeof window === "undefined") return "ssr";
    const saved = localStorage.getItem("gs");
    if (saved) return saved;
    const t = Math.random().toString(36).slice(2) + Date.now().toString(36);
    localStorage.setItem("gs", t);
    return t;
  });
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  async function send() {
    const txt = input.trim();
    if (!txt || busy) return;

    setInput("");
    setMessages((m) => [...m, { role: "user" as const, text: txt }]);
    setBusy(true);

    try {
      const body: Record<string, string> = { message: txt, session_token: token };
      if (bizId) body.business_id = bizId;

      const r = await fetch("/api/ali", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const j = await r.json();

      // Save business_id if returned
      if (j.business_id && !bizId) {
        setBizId(j.business_id);
        localStorage.setItem("gbiz", j.business_id);
      }

      setMessages((m) => [...m, { role: "ali" as const, text: j.response || j.message || "رد فارغ" }]);
    } catch (e) {
      setMessages((m) => [...m, { role: "ali" as const, text: "خطأ بالاتصال: " + String(e) }]);
    }

    setBusy(false);
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", height: "100vh", maxWidth: 600, margin: "0 auto", background: "#030712", color: "#f9fafb" }}>
      {/* Header */}
      <div style={{ padding: 16, borderBottom: "1px solid #333", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <div style={{ fontSize: 18, fontWeight: "bold" }}>جرّب واجهة</div>
          <div style={{ fontSize: 12, color: "#888" }}>ابنِ وكيلك الذكي — بدون تسجيل</div>
        </div>
        <a href="/login" style={{ background: "#16a34a", color: "#fff", padding: "6px 16px", borderRadius: 8, fontSize: 14, textDecoration: "none" }}>
          سجّل
        </a>
      </div>

      {/* Messages */}
      <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
        {messages.map((m, i) => (
          <div key={i} style={{ display: "flex", justifyContent: m.role === "user" ? "flex-start" : "flex-end", marginBottom: 12 }}>
            <div style={{
              background: m.role === "user" ? "#2563eb" : "#1f2937",
              color: "#f9fafb",
              padding: "10px 16px",
              borderRadius: 12,
              maxWidth: "85%",
              fontSize: 14,
              whiteSpace: "pre-wrap",
              lineHeight: 1.6,
            }}>
              {m.text}
            </div>
          </div>
        ))}
        {busy && (
          <div style={{ display: "flex", justifyContent: "flex-end", marginBottom: 12 }}>
            <div style={{ background: "#1f2937", padding: "10px 16px", borderRadius: 12, fontSize: 14, color: "#888" }}>
              علي يكتب...
            </div>
          </div>
        )}
        <div ref={endRef} />
      </div>

      {/* Input */}
      <form
        onSubmit={(e) => { e.preventDefault(); send(); }}
        style={{ padding: 16, borderTop: "1px solid #333", display: "flex", gap: 8 }}
      >
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="اكتب هنا..."
          autoFocus
          style={{
            flex: 1,
            background: "#1f2937",
            color: "#fff",
            border: "none",
            borderRadius: 12,
            padding: "12px 16px",
            fontSize: 16,
            outline: "none",
            direction: "rtl",
            WebkitAppearance: "none",
          }}
        />
        <button
          type="submit"
          disabled={busy}
          style={{
            background: busy ? "#555" : "#2563eb",
            color: "#fff",
            border: "none",
            borderRadius: 12,
            padding: "12px 24px",
            fontSize: 16,
            fontWeight: "bold",
            cursor: busy ? "not-allowed" : "pointer",
          }}
        >
          {busy ? "..." : "إرسال"}
        </button>
      </form>
    </div>
  );
}
