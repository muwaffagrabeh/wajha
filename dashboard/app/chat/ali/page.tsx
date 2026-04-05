"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { sendToAli, getBusinesses } from "@/lib/api";

interface Message {
  role: "user" | "ali";
  content: string;
  source?: string;
}

export default function AliChatPage() {
  const router = useRouter();
  const [messages, setMessages] = useState<Message[]>([
    { role: "ali", content: "أهلاً! أنا علي، مديرك الرقمي. كيف أقدر أساعدك؟" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [bizId, setBizId] = useState<string>("");
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token) { router.push("/login"); return; }
    getBusinesses().then((biz) => {
      if (biz.length > 0) setBizId(biz[0].id);
    }).catch(() => router.push("/login"));
  }, []);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  async function handleSend() {
    if (!input.trim() || loading) return;

    const userMsg = input.trim();
    setInput("");
    setMessages((prev) => [...prev, { role: "user", content: userMsg }]);
    setLoading(true);

    try {
      const res = await sendToAli(userMsg, bizId || undefined);
      setMessages((prev) => [
        ...prev,
        { role: "ali", content: res.response, source: res.source },
      ]);
    } catch (e: unknown) {
      setMessages((prev) => [
        ...prev,
        { role: "ali", content: e instanceof Error ? e.message : "حصل خطأ، حاول مرة ثانية" },
      ]);
    }
    setLoading(false);
  }

  return (
    <div className="flex flex-col h-screen max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-gray-800">
        <div>
          <h1 className="text-lg font-bold">علي</h1>
          <p className="text-xs text-gray-400">مديرك الرقمي</p>
        </div>
        <Link href="/dashboard" className="rounded-lg bg-gray-800 px-3 py-1.5 text-sm hover:bg-gray-700 transition">
          لوحة التحكم
        </Link>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {messages.map((msg, i) => (
          <div key={i} className={`flex ${msg.role === "user" ? "justify-start" : "justify-end"}`}>
            <div className={`max-w-[80%] rounded-xl px-4 py-2.5 ${
              msg.role === "user"
                ? "bg-blue-600"
                : "bg-gray-800"
            }`}>
              <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
              {msg.source && (
                <p className="text-[10px] text-gray-500 mt-1">
                  {msg.source === "gateway" ? "رد فوري" : msg.source === "validator" ? "تحقق" : "ذكاء اصطناعي"}
                </p>
              )}
            </div>
          </div>
        ))}
        {loading && (
          <div className="flex justify-end">
            <div className="bg-gray-800 rounded-xl px-4 py-2.5">
              <p className="text-sm text-gray-400">يكتب...</p>
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="p-4 border-t border-gray-800">
        <div className="flex gap-2">
          <input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && !e.shiftKey && handleSend()}
            placeholder="اكتب رسالتك لعلي..."
            className="flex-1 rounded-xl bg-gray-800 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <button
            onClick={handleSend}
            disabled={loading || !input.trim()}
            className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition"
          >
            إرسال
          </button>
        </div>
      </div>
    </div>
  );
}
