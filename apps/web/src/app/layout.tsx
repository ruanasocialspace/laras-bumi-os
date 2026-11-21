import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Laras Bumi OS",
  description: "Sistem operasional CV Laras Bumi Grup",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="id"><body>{children}</body></html>;
}
