import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "واجهة — لوحة التحكم",
  description: "لوحة تحكم الأنشطة التجارية",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ar" dir="rtl" className="h-full">
      <body className="min-h-full bg-gray-950 text-white">{children}</body>
    </html>
  );
}
