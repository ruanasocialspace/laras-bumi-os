"use client";

import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/feedback";

export default function RouteError({ reset }: { reset: () => void }) {
  return <main className="mx-auto max-w-lg space-y-4 p-6"><Notice>Halaman gagal dimuat.</Notice><Button onClick={reset}>Coba lagi</Button></main>;
}
