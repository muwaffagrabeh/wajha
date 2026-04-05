"use client";

import { useEffect, useRef, useState } from "react";

interface Message { role: "user" | "ali"; text: string }
interface DebugInfo {
  step: string; previous_step: string; sector_type_id: string | null;
  sector_label: string | null; blueprint: string | null; work_model: string | null;
  has_specialists: boolean; service_mode: string | null;
  onboarding_data: Record<string, unknown>;
  terms: Record<string, string> | null;
  terminology: Record<string, unknown> | null;
  llm_output: { raw: string; parsed: Record<string, unknown>; tokens: number } | null;
  validation_errors: string[]; actions_executed: string[];
}

function genToken() { return "test_" + Math.random().toString(36).slice(2) + Date.now().toString(36) }

export default function TestPage() {
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [token, setToken] = useState("");
  const [debug, setDebug] = useState<DebugInfo | null>(null);
  const [debugHistory, setDebugHistory] = useState<DebugInfo[]>([]);
  const [tab, setTab] = useState<"chat" | "debug">("chat");
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => { setToken(genToken()) }, []);
  useEffect(() => { endRef.current?.scrollIntoView({ behavior: "smooth" }) }, [messages, busy]);

  async function send() {
    const txt = input.trim();
    if (!txt || busy) return;
    setInput("");
    setMessages((m) => [...m, { role: "user", text: txt }]);
    setBusy(true);
    try {
      const r = await fetch("/api/onboarding/message", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: txt, session_token: token }),
      });
      const j = await r.json();
      setMessages((m) => [...m, { role: "ali", text: j.response || "..." }]);
      if (j.debug) { setDebug(j.debug); setDebugHistory((h) => [...h, j.debug]) }
    } catch (e) { setMessages((m) => [...m, { role: "ali", text: "خطأ: " + String(e) }]) }
    setBusy(false);
  }

  async function reset() {
    await fetch("/api/onboarding/reset", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ session_token: token }) });
    setToken(genToken()); setMessages([]); setDebug(null); setDebugHistory([]);
  }

  function exportData() {
    const blob = new Blob([JSON.stringify({ messages, debug: debugHistory, token }, null, 2)], { type: "application/json" });
    const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = `onboarding-${token}.json`; a.click();
  }

  // ── Chat Panel ──
  const chatPanel = (
    <div style={{ flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}>
      <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
        {messages.length === 0 && (
          <div style={{ textAlign: "center", color: "#555", marginTop: 40, fontSize: 14 }}>
            ابدأ بكتابة نوع نشاطك...<br />
            <span style={{ fontSize: 12 }}>مثال: عندي صالون نسائي اسمه لمسة بالمدينة</span>
          </div>
        )}
        {messages.map((m, i) => (
          <div key={i} style={{ display: "flex", justifyContent: m.role === "user" ? "flex-start" : "flex-end", marginBottom: 10 }}>
            <div style={{
              background: m.role === "user" ? "#1d4ed8" : "#1a1a2e",
              color: "#e5e5e5",
              padding: "8px 14px", borderRadius: 10, maxWidth: "85%",
              fontSize: 14, whiteSpace: "pre-wrap", lineHeight: 1.7,
              border: m.role === "ali" ? "1px solid #2a2a3e" : "none",
            }}>{m.text}</div>
          </div>
        ))}
        {busy && (
          <div style={{ display: "flex", justifyContent: "flex-end", marginBottom: 10 }}>
            <div style={{ background: "#1a1a2e", padding: "8px 14px", borderRadius: 10, fontSize: 13, color: "#666", border: "1px solid #2a2a3e" }}>...</div>
          </div>
        )}
        <div ref={endRef} />
      </div>
      <form onSubmit={(e) => { e.preventDefault(); send() }} style={{ padding: 12, borderTop: "1px solid #222", display: "flex", gap: 8 }}>
        <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="اكتب هنا..." autoFocus
          style={{ flex: 1, background: "#111", color: "#fff", border: "1px solid #333", borderRadius: 8, padding: "10px 14px", fontSize: 16, outline: "none", direction: "rtl", WebkitAppearance: "none" }} />
        <button type="submit" disabled={busy}
          style={{ background: busy ? "#333" : "#1d4ed8", color: "#fff", border: "none", borderRadius: 8, padding: "10px 18px", fontSize: 16, cursor: busy ? "not-allowed" : "pointer" }}>
          {busy ? "..." : "إرسال"}
        </button>
      </form>
    </div>
  );

  // ── Debug Panel ──
  const debugPanel = (
    <div style={{ flex: 1, overflowY: "auto", padding: 12, fontSize: 12, direction: "ltr", minHeight: 0 }}>
      {debug ? (
        <>
          <Section title="State" color="#8b8bff">
            <Row label="step" value={debug.step} hl />
            <Row label="previous" value={debug.previous_step} />
          </Section>
          <Section title="Sector">
            <Row label="type" value={debug.sector_type_id} />
            <Row label="label" value={debug.sector_label} />
            <Row label="blueprint" value={debug.blueprint} />
            <Row label="work_model" value={debug.work_model} />
            <Row label="specialists" value={debug.has_specialists ? "yes" : "no"} />
            <Row label="service_mode" value={debug.service_mode} />
          </Section>
          {debug.terms && (
            <Section title="Terms">
              {Object.entries(debug.terms).map(([k, v]) => <Row key={k} label={k} value={v} />)}
            </Section>
          )}
          <Section title="Collected Data">
            <pre style={{ margin: 0, fontSize: 11, color: "#aaa", whiteSpace: "pre-wrap", wordBreak: "break-all" }}>
              {JSON.stringify(debug.onboarding_data, null, 2)}
            </pre>
          </Section>
          {debug.llm_output && (
            <Section title="LLM Output" color="#fbbf24">
              <Row label="tokens" value={String(debug.llm_output.tokens)} />
              <pre style={{ margin: "4px 0 0", fontSize: 11, color: "#fbbf24", whiteSpace: "pre-wrap", wordBreak: "break-all", background: "#1a1500", padding: 6, borderRadius: 4 }}>
                {debug.llm_output.raw}
              </pre>
            </Section>
          )}
          {debug.validation_errors.length > 0 && (
            <Section title="Errors" color="#ef4444">
              {debug.validation_errors.map((e, i) => (
                <div key={i} style={{ color: "#fca5a5", fontSize: 11 }}>{typeof e === "string" ? e : JSON.stringify(e)}</div>
              ))}
            </Section>
          )}
          {debug.actions_executed.length > 0 && (
            <Section title="Actions" color="#22c55e">
              {debug.actions_executed.map((a, i) => (
                <div key={i} style={{ color: "#86efac", fontSize: 11 }}>{a}</div>
              ))}
            </Section>
          )}
        </>
      ) : (
        <div style={{ textAlign: "center", color: "#444", marginTop: 40 }}>أرسل رسالة لعرض الـ debug</div>
      )}
    </div>
  );

  return (
    <div style={{ height: "100vh", display: "flex", flexDirection: "column", background: "#0a0a0f", color: "#e5e5e5", fontFamily: "system-ui, sans-serif" }}>
      {/* Header */}
      <div style={{ padding: "8px 12px", borderBottom: "1px solid #222", display: "flex", justifyContent: "space-between", alignItems: "center", flexShrink: 0 }}>
        <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
          <span style={{ fontWeight: "bold", fontSize: 15 }}>اختبار Onboarding</span>
          {debug && <span style={{ fontSize: 11, color: "#8b8bff", background: "#1a1a2e", padding: "2px 8px", borderRadius: 4 }}>{debug.step}</span>}
        </div>
        <div style={{ display: "flex", gap: 6 }}>
          {/* Mobile tabs */}
          <button onClick={() => setTab("chat")}
            style={{ background: tab === "chat" ? "#1d4ed8" : "#222", color: "#fff", border: "none", borderRadius: 6, padding: "4px 10px", fontSize: 12, cursor: "pointer" }}>
            شات
          </button>
          <button onClick={() => setTab("debug")}
            style={{ background: tab === "debug" ? "#1d4ed8" : "#222", color: "#fff", border: "none", borderRadius: 6, padding: "4px 10px", fontSize: 12, cursor: "pointer", position: "relative" }}>
            Debug
            {debug?.llm_output && <span style={{ position: "absolute", top: -2, left: -2, width: 6, height: 6, background: "#fbbf24", borderRadius: "50%" }} />}
          </button>
          <button onClick={reset} style={{ background: "#7f1d1d", color: "#fca5a5", border: "none", borderRadius: 6, padding: "4px 10px", fontSize: 12, cursor: "pointer" }}>Reset</button>
          <button onClick={exportData} style={{ background: "#1e3a5f", color: "#93c5fd", border: "none", borderRadius: 6, padding: "4px 10px", fontSize: 12, cursor: "pointer" }}>Export</button>
        </div>
      </div>

      {/* Desktop: side by side / Mobile: tabs */}
      <div style={{ flex: 1, display: "flex", minHeight: 0, overflow: "hidden" }}>
        {/* Desktop layout */}
        <div className="desktop-only" style={{ flex: 1, display: "flex", minHeight: 0 }}>
          <div style={{ flex: 1, display: "flex", flexDirection: "column", borderLeft: "1px solid #222", minHeight: 0 }}>
            {chatPanel}
          </div>
          <div style={{ width: 380, borderRight: "1px solid #222", background: "#0d0d14", overflowY: "auto" }}>
            {debugPanel}
          </div>
        </div>

        {/* Mobile layout */}
        <div className="mobile-only" style={{ flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}>
          {tab === "chat" ? chatPanel : debugPanel}
        </div>
      </div>

      <style>{`
        @media (min-width: 768px) {
          .mobile-only { display: none !important; }
        }
        @media (max-width: 767px) {
          .desktop-only { display: none !important; }
        }
      `}</style>
    </div>
  );
}

function Section({ title, children, color }: { title: string; children: React.ReactNode; color?: string }) {
  const [open, setOpen] = useState(true);
  return (
    <div style={{ marginBottom: 8, border: "1px solid #222", borderRadius: 6, overflow: "hidden" }}>
      <div onClick={() => setOpen(!open)} style={{ padding: "6px 10px", background: "#151520", cursor: "pointer", display: "flex", justifyContent: "space-between" }}>
        <span style={{ fontWeight: "bold", fontSize: 11, color: color || "#8b8bff" }}>{title}</span>
        <span style={{ fontSize: 10, color: "#555" }}>{open ? "−" : "+"}</span>
      </div>
      {open && <div style={{ padding: "6px 10px" }}>{children}</div>}
    </div>
  );
}

function Row({ label, value, hl }: { label: string; value: string | null | undefined; hl?: boolean }) {
  return (
    <div style={{ display: "flex", justifyContent: "space-between", padding: "2px 0", fontSize: 11 }}>
      <span style={{ color: "#666" }}>{label}</span>
      <span style={{ color: hl ? "#60a5fa" : "#ccc", fontWeight: hl ? "bold" : "normal", fontFamily: "monospace" }}>{value ?? "—"}</span>
    </div>
  );
}
