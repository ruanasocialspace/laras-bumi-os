import type { Metadata } from "next";
import { DriverApp } from "@/features/collection/driver-app";

export const metadata: Metadata = {
  title: "Pengemudi · Laras Bumi OS",
  manifest: "/driver/manifest.webmanifest",
};

export default function DriverPage() {
  return <DriverApp />;
}
