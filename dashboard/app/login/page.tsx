"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { sendOtp, verifyOtp } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [step, setStep] = useState<"phone" | "otp">("phone");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [devCode, setDevCode] = useState("");

  async function handleSendOtp() {
    setLoading(true);
    setError("");
    try {
      const res = await sendOtp(phone);
      if (res.dev_code) setDevCode(res.dev_code);
      setStep("otp");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "خطأ في الإرسال");
    }
    setLoading(false);
  }

  async function handleVerify() {
    setLoading(true);
    setError("");
    try {
      const res = await verifyOtp(phone, code);
      localStorage.setItem("token", res.token);
      localStorage.setItem("user", JSON.stringify(res.user));
      router.push("/dashboard");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "رمز خطأ");
    }
    setLoading(false);
  }

  return (
    <div className="flex min-h-screen items-center justify-center p-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <h1 className="text-3xl font-bold">واجهة</h1>
          <p className="mt-2 text-gray-400">لوحة تحكم النشاط التجاري</p>
        </div>

        <div className="rounded-xl bg-gray-900 p-6 space-y-4">
          {step === "phone" ? (
            <>
              <label className="block text-sm text-gray-400">رقم الجوال</label>
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="05xxxxxxxx"
                className="w-full rounded-lg bg-gray-800 px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                dir="ltr"
              />
              <button
                onClick={handleSendOtp}
                disabled={loading || phone.length < 10}
                className="w-full rounded-lg bg-blue-600 py-3 font-medium hover:bg-blue-700 disabled:opacity-50 transition"
              >
                {loading ? "جاري الإرسال..." : "إرسال رمز التحقق"}
              </button>
            </>
          ) : (
            <>
              <label className="block text-sm text-gray-400">
                رمز التحقق المرسل لـ {phone}
              </label>
              <input
                type="text"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="0000"
                maxLength={4}
                className="w-full rounded-lg bg-gray-800 px-4 py-3 text-center text-2xl tracking-widest text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                dir="ltr"
              />
              {devCode && (
                <p className="text-center text-xs text-yellow-500">
                  رمز التطوير: {devCode}
                </p>
              )}
              <button
                onClick={handleVerify}
                disabled={loading || code.length !== 4}
                className="w-full rounded-lg bg-blue-600 py-3 font-medium hover:bg-blue-700 disabled:opacity-50 transition"
              >
                {loading ? "جاري التحقق..." : "دخول"}
              </button>
              <button
                onClick={() => { setStep("phone"); setCode(""); setDevCode(""); }}
                className="w-full text-sm text-gray-400 hover:text-white"
              >
                تغيير الرقم
              </button>
            </>
          )}

          {error && (
            <p className="text-center text-sm text-red-400">{error}</p>
          )}
        </div>
      </div>
    </div>
  );
}
